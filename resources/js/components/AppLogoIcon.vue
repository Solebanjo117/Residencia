<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/logo-tecnologico-piedras-negras.png"
        alt="Instituto Tecnológico de Piedras Negras"
        :class="className"
        v-bind="$attrs"
    />
</template>
