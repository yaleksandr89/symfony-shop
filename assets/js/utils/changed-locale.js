let $desktopSelect = $("#desktop-select-language");
let $mobileSelect = $("#mobile-select-language");
changedLanguageSite($desktopSelect, $desktopSelect.find("option"));
changedLanguageSite($mobileSelect, $mobileSelect.find("option"));

function changedLanguageSite(selector, childEl) {
  let locale = location.pathname;

  if ("undefined" !== typeof locale || "" !== locale) {
    childEl.each(function () {
      let $this = $(this);
      if (locale.includes($this.attr("value"))) {
        this.selected = true;
      }
    });
  }

  selector.on("change", function (event) {
    event.preventDefault();
    let selectedOptionsIndex = event.target.options.selectedIndex;

    childEl.each(function (key, value) {
      if (key.toString() === selectedOptionsIndex.toString()) {
        let currentLocale = $(value).attr("value").replace(/\//g, "");
        let preparedUrl = $(value).attr("data-url").split("/");
        preparedUrl[1] = currentLocale;

        location.href = preparedUrl.join("/");
      }
    });
    return false;
  });
}
