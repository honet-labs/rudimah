import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { updateMetaCsrfToken } from './utils/csrf';
import '../css/app.css';

router.on('navigate', (event) => {
  const token = event.detail.page.props.csrf_token as string | undefined;
  if (token) {
    updateMetaCsrfToken(token);
  }
});

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
    if (props.initialPage.props.csrf_token) {
      updateMetaCsrfToken(props.initialPage.props.csrf_token as string);
    }
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
