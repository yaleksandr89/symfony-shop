<template>
  <div class="row">
    <div class="col-lg-12 order-block">
      <div class="order-content">
        <Alert/>
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
import {mapActions, mapState} from "vuex";
import Alert from "./components/Alert";

export default {
  name: 'App',
  components: {Alert, CartTotalPrice, CartProductList},
  created() {
    this.getCart();
  },
  computed: {
    ...mapState('cart', ['cart', 'isSentForm']),
    showCartContent() {
      return !this.isSentForm && Object.keys(this.cart).length;
    },
  },
  methods: {
    ...mapActions('cart', ['getCart', 'makeOrder']),
  },
}
</script>
