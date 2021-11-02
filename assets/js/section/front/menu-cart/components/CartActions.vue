<template>
  <div class="actions">
    <a :href="staticStore.url.viewCart" class="btn btn-success">
      View cart
    </a>
    <a :href="'#'" class="btn btn-cancel mt-1" @click.prevent="cleanCart">
      Checkout
    </a>
  </div>
</template>

<script>
import {mapActions, mapState} from "vuex";

export default {
  name: 'CartActions',
  computed: {
    ...mapState('cart', ['staticStore']),
  },
  methods: {
    ...mapActions('cart', ['cleanCart'])
  },
}
</script>
