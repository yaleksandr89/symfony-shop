import "@fontsource/nunito/200.css";
import "@fontsource/nunito/300.css";
import "@fontsource/nunito/400.css";
import "@fontsource/nunito/600.css";
import "@fontsource/nunito/700.css";
import "@fontsource/nunito/800.css";
import "@fontsource/nunito/900.css";
import $ from "jquery";
import "bootstrap";

window.$ = window.jQuery = $;

for (const attribute of ["toggle", "target", "parent", "dismiss"]) {
  for (const element of document.querySelectorAll(`[data-${attribute}]`)) {
    element.setAttribute(
      `data-bs-${attribute}`,
      element.getAttribute(`data-${attribute}`),
    );
  }
}

import "jquery.easing";

import "./js/section/admin/theme/sb-admin-2.js";
import "./js/utils/changed-locale.js";
import "./js/section/admin/theme/filters-feature.js";

import "@fortawesome/fontawesome-free/css/all.min.css";
import "./css/section/admin/libs.scss";
import "./css/section/admin/sb-admin-2.css";
import "./css/section/admin/styles.css";
import "./css/section/admin/main.scss";
