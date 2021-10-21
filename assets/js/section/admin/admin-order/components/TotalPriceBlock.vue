<template>
  <div class="row mb-1">
    <div class="col-md-12 text-right">
      Total price: <span class="font-weight-bold ml-2">${{ totalPrice }}</span>
    </div>
  </div>
</template>

<script>
import {mapState} from "vuex";

export default {
  name: "TotalPriceBlock",
  computed: {
    ...mapState("products",["orderProducts"]),
    totalPrice() {
      let totalPrice = 0;
      this.orderProducts.forEach(orderProducts => {
        totalPrice += parseFloat(orderProducts.pricePerOne) * orderProducts.quantity
      });
      return parseFloat(totalPrice.toFixed(2));
    },
  },
}
</script>

<style scoped>

</style>