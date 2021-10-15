export function getUrlViewProduct(viewUrl, productId) {
    return (
        window.location.protocol +
        '//' +
        window.location.host +
        viewUrl +
        '/' +
        productId
    );
}

export function concatUrlByParams(...params) {
    return params.join('/');
}