<template>
  <div class="total">
    <span>{{ staticStore.localization.total_price }}</span>
    <span class="total-price">${{ totalPrice }}</span>
  </div>
</template>

<script>
import { mapGetters, mapState } from "vuex";

export default {
  name: "CartTotalPrice",
  computed: {
    ...mapState("cart", ["staticStore"]),
    ...mapGetters("cart", ["totalPrice"]),
  },
};
</script>
