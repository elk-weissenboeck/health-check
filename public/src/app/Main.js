import { App } from './App.js';
import { OptionsManager } from '../options/OptionsManager.js';
import { EventBinding } from './EventBinding.js';

const app = new App();
const options = new OptionsManager(app);
app.applyOptions = () => options.apply(); // Inject, damit App.applyOptions existiert

const binder = new EventBinding(app, options);

document.addEventListener('DOMContentLoaded', async () => {
  binder.wire();
  await app.bootstrap();
});
