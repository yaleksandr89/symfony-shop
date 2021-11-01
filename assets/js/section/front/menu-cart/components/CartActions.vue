<template>
  <div class="actions">
    <a :href="'#'" class="btn btn-success">
      View cart
    </a>
    <a :href="'#'" class="btn btn-cancel mt-1">
      Checkout
    </a>
  </div>
</template>

<script>
export default {
  name: 'CartActions'
}
</script>
