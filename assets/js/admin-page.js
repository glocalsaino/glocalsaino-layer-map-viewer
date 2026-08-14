(function () {
    var PALETTE = ['#4daf4a','#4393c3','#f1a340','#d73027','#998ec3','#bf812d','#35978f','#e9a3c9'];
    var I18N = window.GlocalSainoMapAdminI18n || {
        file: 'File', color: 'Layer color', fill: 'Fill', noFill: 'Outline only'
    };

    function buildPickers(input, container) {
        container.innerHTML = '';
        var files = input.files;
        if (!files.length) return;

        var table = document.createElement('table');
        table.style.cssText = 'margin-top:10px;border-collapse:collapse;font-size:13px';

        var header = document.createElement('tr');
        header.innerHTML = '<th style="padding:4px 12px 4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.file + '</th>'
                         + '<th style="padding:4px 12px 4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.color + '</th>'
                         + '<th style="padding:4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.fill + '</th>';
        table.appendChild(header);

        for (var i = 0; i < files.length; i++) {
            var name  = files[i].name.replace(/\.kml$/i, '');
            var color = PALETTE[i % PALETTE.length];
            var tr    = document.createElement('tr');
            tr.innerHTML = '<td style="padding:5px 12px 5px 0">' + escHtml(name) + '</td>'
                         + '<td style="padding:5px 12px 5px 0">'
                         + '<input type="color" name="kml_colors[]" value="' + color + '" '
                         + 'style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer;vertical-align:middle">'
                         + '</td>'
                         + '<td style="padding:5px 0;white-space:nowrap">'
                         + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer">'
                         + '<input type="checkbox" name="kml_no_fill[' + i + ']" value="1"> ' + I18N.noFill
                         + '</label>'
                         + '<input type="range" name="kml_opacity[' + i + ']" min="0" max="100" step="5" value="60" '
                         + 'style="width:60px;vertical-align:middle;margin-left:10px">'
                         + '</td>';
            table.appendChild(tr);
        }
        container.appendChild(table);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Adjuntar a todos los file inputs de la página
    document.querySelectorAll('.kml-file-input').forEach(function (input) {
        var wrap      = input.closest('.kml-file-wrap');
        var container = wrap ? wrap.querySelector('.kml-color-pickers') : null;
        if (!container) return;
        input.addEventListener('change', function () {
            buildPickers(input, container);
        });
    });
})();
