<template>
  <div class="product">
    <div class="product-details">
      <h4 class="product-title">
        <a :href="urlShowProduct" target="_blank">
          {{ cartProduct.product.title }}
        </a>
      </h4>

      <span class="product-info">
          <span class="product-quantity">
            {{ cartProduct.quantity }}
        </span>
        X ${{ cartProduct.product.price }}
      </span>
    </div>
    <figure class="product-image-container">
      <a :href="urlShowProduct" target="_blank">
        <img :src="getUrlProductImage(productImage)" :alt="cartProduct.product.title" class="product-image">
      </a>
    </figure>
    <a href="#" class="btn-remove" title="Remove product" @click.prevent="removeCartProduct(cartProduct.id)">
      X
    </a>
  </div>
</template>

<script>
import {mapActions, mapState} from "vuex";

export default {
  name: 'CartProductItem',
  props: {
    cartProduct: {
      type: Object,
      default: () => {
      },
    },
  },
  computed: {
    ...mapState('cart', ['staticStore']),
    productImage() {
      const productImages = this.cartProduct.product.productImages;
      return productImages.length ? productImages[0] : null;
    },
    urlShowProduct() {
      return this.staticStore.url.viewProduct + '/' + this.cartProduct.product.uuid;
    },
  },
  methods: {
    ...mapActions('cart', ['removeCartProduct']),
    getUrlProductImage(productImage) {
      return (
          this.staticStore.url.assetImageProducts +
          '/' +
          this.cartProduct.product.id +
          '/' +
          productImage.filenameSmall
      );
    },
  },
}
</script>
