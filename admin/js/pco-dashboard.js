(function ($) {
  const root  = elcisCfg.root;
  const nonce = elcisCfg.nonce;

  function fetchCheckins() {
    $.get({
      url: root + '/checkins',
      beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', nonce),
      success: drawTable
    });
  }
  function drawTable(data) {
    function formatRelativeTime(dateString) {
      const created = new Date(dateString);
      const now = new Date();
      const diffMs = now - created;
      const diffMin = Math.round(diffMs / 60000);
      const diffHr = Math.floor(diffMin / 60);
      const mins = diffMin % 60;

      let ago = '';
      if (diffHr > 0) {
        ago = `${diffHr} hr${diffHr > 1 ? 's' : ''}`;
        if (mins > 0) ago += ` ${mins} min${mins > 1 ? 's' : ''}`;
      } else {
        ago = `${diffMin} min${diffMin !== 1 ? 's' : ''}`;
      }

      const day = created.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
      return `${day} (${ago} ago)`;
    }

    console.log('✅ Received check-in data:', data);
    if (!Array.isArray(data) || data.length === 0) {
      $('#elcis-table').html('<p>No current check-ins.</p>');
      return;
    }

    // Collect unique events and rooms
    const events = [...new Set(data.map(i => i.event_name))];
    const rooms  = [...new Set(data.map(i => i.room))];
    const times  = [...new Set(data.map(i => i.event_time))];

    const savedEvent = localStorage.getItem('elcis-event-filter') || '';
    const savedRoom  = localStorage.getItem('elcis-room-filter') || '';
    const savedTime  = localStorage.getItem('elcis-time-filter') || '';

    // Build filters
    let filterHtml = `
      <div style="margin-bottom: 1em;">
        <label>Filter by Event: 
          <select id="elcis-event-filter">
            <option value="">All</option>
            ${events.map(e => `<option value="${e}" ${e === savedEvent ? 'selected' : ''}>${e}</option>`).join('')}
          </select>
        </label>
        <label style="margin-left: 1em;">Filter by Room: 
          <select id="elcis-room-filter">
            <option value="">All</option>
            ${rooms.map(r => `<option value="${r}" ${r === savedRoom ? 'selected' : ''}>${r}</option>`).join('')}
          </select>
        </label>
        <label style="margin-left: 1em;">Filter by Time: 
          <select id="elcis-time-filter">
            <option value="">All</option>
            ${times.map(t => `<option value="${t}" ${t === savedTime ? 'selected' : ''}>${t}</option>`).join('')}
          </select>
        </label>
      </div>
    `;

    let html = filterHtml + '<table class="wp-list-table widefat fixed striped">';
    html += '<thead><tr><th>Name</th><th>Since</th><th>Room</th><th>Phone</th><th>Checked In By</th><th>Service Time</th><th>SMS</th></tr></thead><tbody>';

    data.forEach(item => {
      html += `<tr data-event="${item.event_name}" data-room="${item.room}" data-time="${item.event_time}">
        <td>${item.name}</td>
        <td>${formatRelativeTime(item.created_at)}</td>
        <td>${item.room}</td>
        <td>${item.phone}</td>
        <td>${item.checked_in_by || '—'}</td>
        <td>${item.event_time || '—'}</td>
        <td><button class="elcis-sms button" data-phone="${item.phone}" data-kid="${item.name}" data-room="${item.room}">SMS</button></td>
      </tr>`;
    });

    html += '</tbody></table>';
    $('#elcis-table').html(html);

    // Apply saved filters after refresh
    applySavedFilters();

    function applySavedFilters() {
      const eventFilter = $('#elcis-event-filter').val();
      const roomFilter  = $('#elcis-room-filter').val();
      const timeFilter  = $('#elcis-time-filter').val();

      $('#elcis-table tbody tr').each(function () {
        const matchesEvent = !eventFilter || $(this).data('event') === eventFilter;
        const matchesRoom  = !roomFilter  || $(this).data('room')  === roomFilter;
        const matchesTime  = !timeFilter || $(this).data('time') === timeFilter;
        $(this).toggle(matchesEvent && matchesRoom && matchesTime);
      });
    }

    // Filtering logic
    $('#elcis-event-filter, #elcis-room-filter, #elcis-time-filter').on('change', function () {
      const eventFilter = $('#elcis-event-filter').val();
      const roomFilter  = $('#elcis-room-filter').val();
      const timeFilter  = $('#elcis-time-filter').val();
      localStorage.setItem('elcis-event-filter', eventFilter);
      localStorage.setItem('elcis-room-filter', roomFilter);
      localStorage.setItem('elcis-time-filter', timeFilter);

      $('#elcis-table tbody tr').each(function () {
        const matchesEvent = !eventFilter || $(this).data('event') === eventFilter;
        const matchesRoom  = !roomFilter  || $(this).data('room')  === roomFilter;
        const matchesTime  = !timeFilter || $(this).data('time') === timeFilter;
        $(this).toggle(matchesEvent && matchesRoom && matchesTime);
      });
    });
  }

  $(document).on('click', '.elcis-sms', function () {
    const kid  = $(this).data('kid');
    const room = $(this).data('room');
    const to   = $(this).data('phone');
    $.get({
      url: root + '/sms-template',
      beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', nonce),
      success: template => {
        const body = template.replace('{kid}', kid).replace('{room}', room);
        const customBody = prompt("Send this message?", body);
        if (!customBody) return;

        $.post({
          url: root + '/sms',
          beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', nonce),
          data: { to, body: customBody },
          success: () => alert('Text sent')
        });
      }
    });
  });

  fetchCheckins();
  setInterval(fetchCheckins, 20000);
})(jQuery);