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
    const rows = data.data.map(c => {
      const kid  = c.attributes.person_name;
      const room = c.attributes.location_name;
      const time = new Date(c.attributes.created_at).toLocaleTimeString();
      const phone= c.attributes.guardian_phone || '—';
      const btn  = phone !== '—'
        ? `<button class="elcis-sms" data-phone="${phone}" data-kid="${kid}" data-room="${room}">SMS</button>`
        : '';
      return `<tr><td>${kid}</td><td>${room}</td><td>${time}</td><td>${phone}</td><td>${btn}</td></tr>`;
    }).join('');
    $('#elcis-table').html('<table><thead><tr><th>Name</th><th>Room</th><th>Since</th><th>Phone</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>');
  }

  $(document).on('click', '.elcis-sms', function () {
    const to   = $(this).data('phone');
    const body = `${$(this).data('kid')} needs you at ${$(this).data('room')}. Please come now.`;
    $.post({
      url: root + '/sms',
      beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', nonce),
      data: { to, body },
      success: () => alert('Text sent')
    });
  });

  fetchCheckins();
  setInterval(fetchCheckins, 20000);
})(jQuery);