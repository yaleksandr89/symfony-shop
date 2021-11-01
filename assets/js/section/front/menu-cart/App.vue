<template>
  <div class="dropdown cart-dropdown">
    <a href="#" class="cart-dropdown-btn-toggle">
      <i class="fas fa-shopping-cart"></i>
      <span class="count">15</span>
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

export default {
  name: "App",
  components: {CartProductList, CartActions, CartTotalPrice},
  computed: {},
  methods: {},
}
</script>
