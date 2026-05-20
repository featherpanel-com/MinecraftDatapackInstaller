import { createRouter, createWebHashHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";
import { resolveServerUuid } from "@/composables/useServerUuid";

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

router.beforeEach((to, _from, next) => {
  if (to.name === "Error") {
    next();
    return;
  }

  if (!resolveServerUuid()) {
    next({ name: "Error" });
    return;
  }

  next();
});

export default router;
