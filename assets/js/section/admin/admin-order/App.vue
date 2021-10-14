<template>
  <div>
    Vue app: {{ productsCount }}
  </div>
</template>

<script>
export default {
  computed: {
    productsCount: () => {
      return 123;
    }
  }
}
</script>