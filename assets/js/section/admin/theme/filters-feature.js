import {getCookie, setCookie} from "../../../utils/cookie-manager";

window.toggleFiltersVisibility = function toggleFiltersVisibility(section) {
    const filtersKey = 'filtersVisible_' + section;
    const filtersSaveValue = getCookie(filtersKey);

    const visibleValue = filtersSaveValue === 'false';

    setCookie(filtersKey, visibleValue, {
        secure: true,
        'max-age': 3600,
    });
};

window.changeFiltersBlockView = function changeFiltersBlockView(filterSection, element) {
    const filtersKey = 'filtersVisible_' + filterSection;
    const filtersSaveValue = getCookie(filtersKey);

    element.style.display = filtersSaveValue === 'false' ? 'block' : 'none';
};