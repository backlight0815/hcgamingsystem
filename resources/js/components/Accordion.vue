<template>
    <div>
      <div
        class="accordion-header"
        @click="toggleAccordion"
      >
        {{ title }}
      </div>
      <div class="accordion-content" v-if="expanded">
        <slot></slot>
      </div>
    </div>
  </template>

  <script>
  export default {
    data() {
      return {
        expanded: false,
      };
    },
    props: {
      title: String,
    },
    methods: {
      toggleAccordion() {
        this.expanded = !this.expanded;
      },
    },
  };
  </script>

  <style scoped>
  /* Add your styling here */
  </style>
