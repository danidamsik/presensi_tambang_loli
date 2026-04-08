<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 focus:border-amber-500 focus:ring-amber-500/30 rounded-xl shadow-sm transition-all duration-300 py-3 px-4 focus:bg-white focus:shadow-[0_0_15px_rgba(245,158,11,0.15)]"
        v-model="model"
        ref="input"
    />
</template>
