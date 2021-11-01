<template>
  <div class="dropdown cart-dropdown">
    <a href="#" class="cart-dropdown-btn-toggle">
      <i class="fas fa-shopping-cart"></i>
      <span class="count">{{ countCartProducts }}</span>
    </a>

    <div class="dropdown-menu cart-dropdown-window">
      <CartProductList/>
      <CartTotalPrice/>
      <CartActions/>
    </div>
  </div>
</template>

<script>
import CartTotalPrice from "./components/CartTotalPrice";
import CartActions from "./components/CartActions";
import CartProductList from "./components/CartProductList";
import {mapActions, mapState} from 'vuex';

export default {
  name: 'App',
  components: {CartProductList, CartActions, CartTotalPrice},
  created() {
    this.getCart();
  },
  computed: {
    ...mapState('cart', ['cart']),
    countCartProducts() {
      return this.cart.cartProducts
          ? this.cart.cartProducts.length
          : 0;
    },
  },
  methods: {
    ...mapActions('cart', ['getCart'])
  },
}
</script>
