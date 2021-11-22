let $select = $('#select-language');
let $options = $select.find('option');
let locale = location.pathname;
let referrer = document.referrer;

if ('undefined' !== typeof (locale) || '' !== locale) {
    $options.each(function () {
        let $this = $(this);
        if (locale.includes($this.attr('value'))) {
            this.selected = true;
        }
    });
}

$select.on('change', function (event) {
    event.preventDefault();
    let selectedOptionsIndex = event.target.options.selectedIndex;

    $options.each(function (key, value) {
        if (key.toString() === selectedOptionsIndex.toString()) {
            let currentLocale = $(this).attr('value').replace( /\//g, '' )
            let preparedUrl = $(this).attr('data-url').split('/');
            preparedUrl[1] = currentLocale;

            location.href = preparedUrl.join('/');
        }
    });
    return false;
});
