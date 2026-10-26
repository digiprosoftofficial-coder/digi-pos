import '../css/app.css';
import { initCapacitor } from '@/lib/capacitor-init';

initCapacitor().finally(() => {
    import('./app.js');
});
