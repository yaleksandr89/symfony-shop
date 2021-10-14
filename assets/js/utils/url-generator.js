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