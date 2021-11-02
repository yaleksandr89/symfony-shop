<template>
  <div class="products">
    <CartProductItem
        v-for="cartProduct in cart.cartProducts"
        :cart-product="cartProduct"
        :key="cartProduct.id"
    />
  </div>
</template>

<script>
import CartProductItem from "./CartProductItem";
import {mapState} from "vuex";

export default {
  name: 'CartProductList',
  components: {CartProductItem},
  computed: {
    ...mapState('cart', ['cart']),
  },
}
</script>
