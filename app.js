(function () {
  var body = document.body;
  var t = document.getElementById('menu-toggle');
  var b = document.getElementById('backdrop');
  if (t) t.addEventListener('click', function () { body.classList.toggle('sidebar-open'); });
  if (b) b.addEventListener('click', function () { body.classList.remove('sidebar-open'); });

  // filter tabel: <input data-filter="#idTabel">
  document.querySelectorAll('input[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      document.querySelectorAll(inp.getAttribute('data-filter') + ' tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
      });
    });
  });

  // konfirmasi aksi: <form data-confirm="Yakin?">
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  // baris dinamis untuk form transaksi
  var lines = document.getElementById('lines');
  var tpl = document.getElementById('line-tpl');
  var add = document.getElementById('add-line');

  function syncRow(tr) {
    var sel = tr.querySelector('select.barang-select');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var info = tr.querySelector('.row-info');
    var unit = tr.querySelector('.row-unit');
    if (unit) unit.textContent = opt && opt.value ? opt.getAttribute('data-satuan') : '';
    var harga = tr.querySelector('input.harga-input');
    if (harga && opt && opt.value && !harga.value) harga.value = opt.getAttribute('data-harga') || '';
    if (info) {
      if (opt && opt.value && opt.hasAttribute('data-stok')) {
        var exp = opt.getAttribute('data-exp');
        info.textContent = 'Stok: ' + opt.getAttribute('data-stok') + ' ' + opt.getAttribute('data-satuan') +
          (exp ? ' | FEFO: kedaluwarsa terdekat ' + exp : '');
      } else {
        info.textContent = '';
      }
    }
  }

  function addRow() {
    if (!lines || !tpl) return;
    var node = tpl.content.firstElementChild.cloneNode(true);
    lines.appendChild(node);
    return node;
  }

  if (add) add.addEventListener('click', function () { addRow(); });
  if (lines) {
    lines.addEventListener('change', function (ev) {
      if (ev.target.classList.contains('barang-select')) syncRow(ev.target.closest('tr'));
    });
    lines.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.rm-line');
      if (!btn) return;
      if (lines.children.length > 1) btn.closest('tr').remove();
    });
    if (lines.children.length === 0) addRow();
    Array.prototype.forEach.call(lines.children, syncRow);
  }
})();
