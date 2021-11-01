<template>
  <table class="table table-cart table-mobile main-cart-show">
    <thead>
    <tr>
      <td>Product</td>
      <td>Price</td>
      <td>Quantity</td>
      <td>Total</td>
      <td>Actions</td>
    </tr>
    </thead>
    <tbody>
    <CartProductItem
        v-for="cartProduct in cart.cartProducts"
        :key="cartProduct.id"
        :cart-product="cartProduct"
    />
    </tbody>
  </table>
</template>

<script>
import CartProductItem from "./CartProductItem";
import {mapState} from "vuex";

export default {
  name: "CartProductList",
  components: {CartProductItem},
  computed: {
    ...mapState('cart', ['cart']),
  },
}
</script>
