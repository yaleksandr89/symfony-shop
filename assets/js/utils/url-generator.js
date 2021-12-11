export function getUrlViewProduct(viewUrl, productId) {
  return (
    window.location.protocol +
    "//" +
    window.location.host +
    viewUrl +
    "/" +
    productId
  );
}

export function concatUrlByParams(...params) {
  return params.join("/");
}

export function getUrlProductsByCategory(
  defaultUrl,
  categoryId,
  page,
  itemsPerPage
) {
  return (
    defaultUrl +
    "?category=/api/categories/" +
    categoryId +
    "&isPublished=true" +
    "&page=" +
    page +
    "&itemsPerPage=" +
    itemsPerPage
  );
}
