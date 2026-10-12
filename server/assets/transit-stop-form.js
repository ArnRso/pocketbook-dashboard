// Formulaire d'un arrêt TBM : la liste « Ligne et direction » ne propose que les lignes qui desservent
// l'arrêt choisi. EasyAdmin crée les listes TomSelect au chargement de la page.
const routes = document.querySelector('select[data-routes-url]');
const stop = routes?.form?.querySelector('select[name$="[stopArea]"]');

const filter = async () => {
    if (!stop.value) {
        return;
    }

    const response = await fetch(`${routes.dataset.routesUrl}?${new URLSearchParams({ stop: stop.value })}`);
    if (!response.ok) {
        return;
    }
    const choices = await response.json();

    const select = routes.tomselect;
    const current = select.getValue();
    select.clear(true);
    select.clearOptions();
    select.addOptions(choices);
    if (choices.some((choice) => choice.value === current)) {
        select.setValue(current, true);
    }
};

if (routes && stop) {
    window.addEventListener('load', () => {
        stop.addEventListener('change', filter);
        filter();
    });
}
