<template>
  <div class="row">
    <div class="col-lg-12 order-block">
      <div class="order-content">
        <div v-if="showCartContent">
          <CartProductList/>
          <CartTotalPrice/>
          <a href="#"
             class="btn btn-success mb-3 text-white"
             @click.prevent="makeOrder"
          >
            MAKE ORDER
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import CartProductList from "./components/CartProductList";
import CartTotalPrice from "./components/CartTotalPrice";

export default {
  name: "App",
  components: {CartTotalPrice, CartProductList},
  computed: {
    showCartContent() {
      return true;
    },
  },
  methods: {
    makeOrder() {
      return true;
    }
  },
}
</script>
