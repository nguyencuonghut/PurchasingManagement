import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import AppLayout from '@/PrimeVue/layout/AppLayout.vue';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Toast from 'primevue/toast';
import SelectButton from 'primevue/selectbutton';
import StyleClass from 'primevue/styleclass';

import '@/PrimeVue/assets/styles.scss';
import '@/PrimeVue/assets/tailwind.css';

createInertiaApp({
  resolve: async (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue');
    const importPage = pages[`./Pages/${name}.vue`];
    if (!importPage) {
      throw new Error(`Page not found: ${name}`);
    }
    const page = await importPage();
    if (page.default.layout === undefined) {
      page.default.layout = AppLayout;
    }
    return page;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(PrimeVue, {
        theme: {
          preset: Aura,
          options: {
            prefix: 'p',
            darkModeSelector: '.app-dark',
            cssLayer: false,
          },
        },
      })
      .use(ToastService)
      .use(ConfirmationService)
      .component('Toast', Toast)
      .component('SelectButton', SelectButton)
      .directive('styleclass', StyleClass)
      .mount(el);
  },
})
