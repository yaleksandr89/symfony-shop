<template>
  <div class="total">
    <span>Total</span>
    <span class="total-price">${{ totalPrice }}</span>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';

export default {
  name: 'CartTotalPrice',
  computed: {
    ...mapGetters('cart', ['totalPrice'])
  },
}
</script>
