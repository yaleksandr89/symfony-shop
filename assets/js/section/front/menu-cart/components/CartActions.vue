<template>
  <div class="actions">
    <a :href="staticStore.url.viewCart" class="btn btn-success">
      {{ staticStore.localization.view_cart }}
    </a>
    <a :href="'#'" class="btn btn-cancel mt-1" @click.prevent="cleanCart">
      {{ staticStore.localization.checkout }}
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
    ...mapActions('cart', ['cleanCart']),
  },
}
</script>
