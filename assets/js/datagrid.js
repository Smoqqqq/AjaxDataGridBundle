import '../styles/datagrid.scss';

class DataGrid {
    constructor(id) {
        this.id = id;
        this.filterForm = document.getElementById(`${this.id}-datagrid-filter-form`);
        this.table = document.querySelector('.table');
        this.thead = table.querySelector('thead');
        this.tableHolder = document.getElementById(`${this.id}-datagrid-table-holder`);

        this.filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            search();
        });
    }

    async getPageData() {
        const queryString = new URLSearchParams(new FormData(this.filterForm)).toString()

        const url = '/station-datagrid/ajax?' + queryString;

        return await fetch(url)
            .then(res => res.json());
    }

    async constructNewTable() {
        this.tableHolder.classList.add('loading');

        await this.getPageData().then(data => {
            const tbody = this.table.querySelector('tbody');

            tbody.innerHTML = '';

            for (const item of data.items) {
                const tr = document.createElement('tr');

                for (const field of item.data) {
                    const td = document.createElement('td');
                    td.textContent = field;
                    tr.appendChild(td);
                }

                const td = document.createElement('td');
                for (const field of item.actions) {
                    td.innerHTML += `<a href="${field.url}">${field.label}</a> `;
                }
                tr.appendChild(td);

                tbody.appendChild(tr);
            }

            if (data.items.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.setAttribute('colspan', this.thead.querySelectorAll('th').length + 1);
                td.textContent = 'Aucun résultat';
                tr.appendChild(td);
                tbody.appendChild(tr);
            }

            this.updatePagination(data);

            this.tableHolder.classList.remove('loading');
        })
    }

    goToPage(i) {
        document.getElementById('form__page').value = i;
        this.constructNewTable();
    }

    updatePagination(data) {
        if (document.getElementById(`${this.id}-datagrid-pagination`) === null) return;

        const paginationUl = document.querySelector('#datagrid-pagination ul');

        paginationUl.innerHTML = '';

        const prev = document.createElement('li');
        prev.classList.add('page-item');
        prev.innerHTML = `<div class="page-link">Précédent</div>`;
        paginationUl.appendChild(prev);

        for (let i = 1; i <= data.nbPages; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item');
            li.onclick = () => goToPage(i);
            li.dataset.nb = i;
            li.innerHTML = `<div class="page-link">${i}</div>`;
            paginationUl.appendChild(li);
        }

        const next = document.createElement('li');
        next.classList.add('page-item');
        next.innerHTML = `<div class="page-link">Suivant</div>`;
        paginationUl.appendChild(next);

        document.querySelector('.page-item[data-nb="' + data.currentPage + '"]')?.classList.add('active');

        if (data.currentPage > 1) {
            prev.classList.remove('disabled');
            prev.onclick = () => goToPage(data.currentPage - 1);
        } else {
            prev.classList.add('disabled');
        }

        if (data.currentPage < data.nbPages) {
            next.classList.remove('disabled');
            next.onclick = () => goToPage(data.currentPage + 1);
        } else {
            next.classList.add('disabled');
        }
    }

    search() {
        document.getElementById('form__page').value = 1;    // reset page to 1
        this.constructNewTable();                                         // load data
    }
}

window._datagrid_search = search;
window._datagrid_goToPage = goToPage;

console.log("HOURA")