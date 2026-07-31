<script setup lang="ts">defineProps<{value?:string}>()</script><template><span class="badge" :class="`badge-${value||'unknown'}`">{{value||'—'}}</span></template>
