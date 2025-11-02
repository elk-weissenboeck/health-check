import { Templates } from './Templates.js';

export class Renderer {
  constructor(containerId = 'groupsContainer') {
    this.container = document.getElementById(containerId);
  }

  renderGroups(groups) {
    if (!this.container) return;
    this.container.innerHTML = groups.map(g => Templates.groupCard(g)).join('');
  }
}
