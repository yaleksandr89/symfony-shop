<template>
  <div class="dropdown cart-dropdown">
    <a href="#" class="cart-dropdown-btn-toggle">
      <i class="fas fa-shopping-cart"></i>
      <span class="count">{{ countCartProducts }}</span>
    </a>

    <div class="dropdown-menu cart-dropdown-window">
      <CartProductList />
      <CartTotalPrice />

      <div v-if="countCartProducts">
        <CartActions />
      </div>
      <div v-else class="text-center">
        {{ staticStore.localization.cart_empty }}
      </div>
    </div>
  </div>
</template>

<script>
import CartTotalPrice from "./components/CartTotalPrice.vue";
import CartActions from "./components/CartActions.vue";
import CartProductList from "./components/CartProductList.vue";
import { mapActions, mapState } from "vuex";

export default {
  name: "MenuCartApp",
  components: { CartProductList, CartActions, CartTotalPrice },
  computed: {
    ...mapState("cart", ["cart", "staticStore"]),
    countCartProducts() {
      return this.cart.cartProducts ? this.cart.cartProducts.length : 0;
    },
  },
  created() {
    this.getCart();
  },
  methods: {
    ...mapActions("cart", ["getCart"]),
  },
};
</script>
