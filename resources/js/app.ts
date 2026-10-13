import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

createInertiaApp({
  resolve: (name: string) => {
    const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
    const page = pages[`./pages/${name}.vue`] as any;
    if (!page) {
      throw new Error(`Halaman ${name} tidak ditemukan dalam direktori resources/js/pages`);
    }
    return page.default || page;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
