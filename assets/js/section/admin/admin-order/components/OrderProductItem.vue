<template>
  <div class="row mb-1">
    <div class="col-md-1 text-center">
      {{ rowNumber }}
    </div>
    <div class="col-md-4">
      {{ productTitle }}
    </div>
    <div class="col-md-2">
      {{ categoryTitle }}
    </div>
    <div class="col-md-1">
      {{ quantity }}
    </div>
    <div class="col-md-2">
      {{ pricePerOne }}
    </div>
    <div class="col-md-2">
      <button class="btn btn-sm btn-outline-info" @click.prevent="viewDetails">
        Details
      </button>
      <button class="btn btn-sm btn-outline-danger" @click.prevent="remove">
        Remove
      </button>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from "vuex";
import { getUrlViewProduct } from "../../../../utils/url-generator";
import { getProductInformativeTitle } from "../../../../utils/title-formatter";

export default {
  name: "OrderProductItem",
  props: {
    orderProduct: {
      type: Object,
      default: () => {},
    },
    index: {
      type: Number,
      default: 0,
    },
  },
  computed: {
    ...mapState("products", ["staticStore"]),
    rowNumber() {
      return this.index + 1;
    },
    productTitle() {
      return getProductInformativeTitle(this.orderProduct.product);
    },
    categoryTitle() {
      return this.orderProduct.product.category.title;
    },
    quantity() {
      return this.orderProduct.quantity;
    },
    pricePerOne() {
      return "$" + this.orderProduct.pricePerOne;
    },
  },
  methods: {
    ...mapActions("products", ["removeOrderProduct"]),
    viewDetails(event) {
      const url = getUrlViewProduct(
        this.staticStore.url.viewProduct,
        this.orderProduct.product.id
      );
      window.open(url, "_blank").focus();
    },
    remove(event) {
      this.removeOrderProduct(this.orderProduct.id);
    },
  },
};
</script>
