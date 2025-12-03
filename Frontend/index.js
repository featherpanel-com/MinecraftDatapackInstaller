// ===============================================
// MinecraftDataPackInstaller Plugin - Frontend JavaScript
// ===============================================

console.log("🚀 MinecraftDataPackInstaller Plugin Loading...");

// Wait for FeatherPanel API to be available
function waitForAPI() {
  return new Promise((resolve) => {
    if (window.FeatherPanel && window.FeatherPanel.api) {
      resolve();
    } else {
      // Check every 100ms until API is available
      const check = setInterval(() => {
        if (window.FeatherPanel && window.FeatherPanel.api) {
          clearInterval(check);
          resolve();
        }
      }, 100);
    }
  });
}

// Main MinecraftDataPackInstaller Plugin Class
class MinecraftDataPackInstallerPlugin {
  constructor() {
    this.api = null;
  }

  async init(api) {
    this.api = api;
    console.log("🚀 MinecraftDataPackInstaller Plugin initialized!");
  }
}

// Main plugin initialization
async function initMinecraftDataPackInstallerPlugin() {
  await waitForAPI();

  const api = window.FeatherPanel.api;
  const MinecraftDataPackInstallerPluginInstance = new MinecraftDataPackInstallerPlugin();
  await MinecraftDataPackInstallerPluginInstance.init(api);

  // Make plugin globally available
  window.MinecraftDataPackInstallerPlugin = MinecraftDataPackInstallerPluginInstance;

  console.log("🚀 MinecraftDataPackInstaller Plugin API Ready!");
}

// Initialize the plugin
initMinecraftDataPackInstallerPlugin();

console.log("🚀 MinecraftDataPackInstaller Plugin script loaded");
