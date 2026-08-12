<template>
  <table class="table table-cart table-mobile main-cart-show">
    <thead>
      <tr>
        <td>{{ staticStore.localization.product }}</td>
        <td>{{ staticStore.localization.price }}</td>
        <td>{{ staticStore.localization.quantity }}</td>
        <td>{{ staticStore.localization.total }}</td>
        <td>{{ staticStore.localization.actions }}</td>
      </tr>
    </thead>
    <tbody>
      <CartProductItem
        v-for="cartProduct in cart.cartProducts"
        :key="cartProduct.id"
        :cart-product="cartProduct"
        :unavailable-reason="unavailableReason(cartProduct.id)"
      />
    </tbody>
  </table>
</template>

<script>
import CartProductItem from "./CartProductItem";
import { mapGetters, mapState } from "vuex";

export default {
  name: "CartProductList",
  components: { CartProductItem },
  computed: {
    ...mapState("cart", ["cart", "staticStore"]),
    ...mapGetters("cart", ["unavailableReason"]),
  },
};
</script>
