<template>
  <div class="row mb-2">
    <div class="col-md-2">
      <select
          v-model="form.categoryId"
          name="add_product_category_select"
          class="form-control"
          @change="getProducts()"
      >
        <option value="" disabled>- choose options-</option>
        <option
            v-for="category in categories"
            :key="category.id"
            :value="category.id"
        >
          {{ category.title }}
        </option>
      </select>
    </div>

    <div class="col-md-3">
      <select
          v-model="form.productId"
          name="add_product_product_select"
          class="form-control"
      >
        <option value="" disabled>- choose options-</option>
        <option
            v-for="categoryProduct in categoryProducts"
            :key="categoryProduct.id"
            :value="categoryProduct.uuid"
        >
          {{ productTitle(categoryProduct) }}
        </option>
      </select>
    </div>
    <div class="col-md-2">
      <input
          v-model="form.quantity"
          type="number"
          class="form-control"
          placeholder="quantity"
          min="0"
      >
    </div>
    <div class="col-md-2">
      <input
          v-model="form.pricePerOne"
          type="number"
          class="form-control"
          placeholder="price per one"
          step="0.01"
          min="0"
      >
    </div>
    <div class="col-md-3">
      <button
          class="btn btn-sm btn-outline-info"
          @click.prevent="viewDetails"
      >
        Details
      </button>
      <button
          class="btn btn-sm btn-outline-success"
          @click.prevent="submit"
      >
        Add
      </button>
    </div>
  </div>
</template>

<script>
import {mapActions, mapMutations, mapState} from "vuex";
import {getProductInformativeTitle} from "../../../../utils/title-formatter";
import {getUrlViewProduct} from "../../../../utils/url-generator";

export default {
  name: "OrderProductAdd",
  data() {
    return {
      form: {
        categoryId: "",
        productId: "",
        quantity: "",
        pricePerOne: "",
      }
    };
  },
  computed: {
    ...mapState("products", ["categories", "categoryProducts", "staticStore"]),
  },
  methods: {
    ...mapMutations("products", ["setNewProductInfo"]),
    ...mapActions("products", ["getProductsByCategory", "addNewOrderProduct"]),
    getProducts() {
      this.setNewProductInfo(this.form);
      this.getProductsByCategory();
    },
    productTitle(product) {
      return getProductInformativeTitle(product);
    },
    viewDetails(event) {
      const url = getUrlViewProduct(
          this.staticStore.url.viewProduct,
          this.form.productId
      );
      window.open(url, '_blank').focus();
    },
    submit(event) {
      this.setNewProductInfo(this.form);
      this.addNewOrderProduct();
      this.resetFormData();
    },
    resetFormData() {
      Object.assign(this.$data, this.$options.data.apply(this));
    },
  },
}
</script>