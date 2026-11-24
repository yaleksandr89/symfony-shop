import js from "@eslint/js";
import prettier from "eslint-plugin-prettier/recommended";
import vue from "eslint-plugin-vue";
import globals from "globals";

export default [
  js.configs.recommended,
  ...vue.configs["flat/recommended"],
  {
    files: ["assets/**/*.{js,vue}"],
    languageOptions: {
      globals: { ...globals.browser, ...globals.node, ...globals.jquery },
    },
  },
  prettier,
];
