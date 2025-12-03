import { createRouter, createWebHashHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";

// Function to extract server UUID from URL query string
function getServerUuidFromUrl(): string | null {
  const params = new URLSearchParams(window.location.search);
  const uuid = params.get("serverUuid") || null;
  console.log("Extracted server UUID from query:", uuid);
  return uuid;
}

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    name: "DataPackInstaller",
    component: () => import("@/pages/DataPackInstaller.vue"),
  },
  {
    path: "/error",
    name: "Error",
    component: () => import("@/pages/Error.vue"),
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

// Check for serverUuid before each route
router.beforeEach((to, _from, next) => {
  const serverUuid = getServerUuidFromUrl();

  // Allow access to error page without serverUuid
  if (to.name === "Error") {
    next();
    return;
  }

  // Check if serverUuid is missing
  if (!serverUuid) {
    console.error("Missing serverUuid parameter in URL");
    next({ name: "Error" });
    return;
  }

  next();
});

export default router;
