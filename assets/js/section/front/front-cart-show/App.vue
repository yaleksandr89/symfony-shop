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
import {mapActions} from "vuex";

export default {
  name: "App",
  components: {CartTotalPrice, CartProductList},
  created() {
    this.getCart();
  },
  computed: {
    showCartContent() {
      return true;
    },
  },
  methods: {
    ...mapActions('cart', ['getCart']),
    makeOrder() {
      return true;
    }
  },
}
</script>
