// Éditeur de grille d'un dashboard : chaque déplacement ou redimensionnement est enregistré aussitôt,
// puis l'aperçu est rechargé. Une modification refusée par le serveur est annulée en rechargeant la page.
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';

const element = document.querySelector('[data-grid-editor]');

if (element) {
    const { columns, rows, csrf } = element.dataset;
    const preview = document.querySelector('[data-grid-preview]');
    const previewSource = preview.getAttribute('src');

    const grid = GridStack.init(
        {
            column: Number(columns),
            maxRow: Number(rows),
            minRow: Number(rows),
            cellHeight: 'auto',
            margin: 3,
            float: true,
            animate: false,
        },
        element,
    );

    const save = async (item) => {
        const response = await fetch(item.el.dataset.layoutUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ column: item.x + 1, row: item.y + 1, width: item.w, height: item.h }),
        });
        if (response.ok) {
            return;
        }
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message ?? error.detail ?? 'Enregistrement impossible.');
    };

    grid.on('change', async (event, items) => {
        try {
            for (const item of items) {
                await save(item);
            }
            preview.src = `${previewSource}${previewSource.includes('?') ? '&' : '?'}t=${Date.now()}`;
        } catch (error) {
            window.alert(error.message);
            window.location.reload();
        }
    });
}

for (const form of document.querySelectorAll('form[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}
