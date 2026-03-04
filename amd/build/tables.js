define([], function () {

    return {
        init: function () {

            document.querySelectorAll('.js-sort-completed').forEach(function (header) {

                let asc = true;
                const columnIndex = parseInt(header.dataset.column);
                const indicator = header.querySelector('.sort-indicator');

                header.style.cursor = 'pointer';

                header.addEventListener('click', function () {

                    const table = header.closest('table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.sort(function (a, b) {

                        let valA = parseInt(a.children[columnIndex].textContent.trim());
                        let valB = parseInt(b.children[columnIndex].textContent.trim());

                        return asc ? valA - valB : valB - valA;
                    });

                    rows.forEach(function (row) {
                        tbody.appendChild(row);
                    });

                    // Atualiza ícone
                    indicator.textContent = asc ? '▲' : '▼';

                    asc = !asc;
                });

            });

        }
    };
});