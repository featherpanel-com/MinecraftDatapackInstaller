import { computed } from "vue";

function getCookie(name: string): string | null {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) {
    return parts.pop()?.split(";").shift() || null;
  }
  return null;
}

function isValidUuid(value: string | null | undefined): value is string {
  return (
    !!value &&
    value !== "notFound" &&
    value !== "testData" &&
    value !== "null" &&
    value.trim() !== ""
  );
}

export function resolveServerUuid(): string | null {
  const params = new URLSearchParams(window.location.search);
  const fromQuery = params.get("serverUuid");
  if (isValidUuid(fromQuery)) {
    return fromQuery;
  }

  const pathMatch = window.location.pathname.match(/\/server\/([a-f0-9-]+)/i);
  if (pathMatch?.[1]) {
    return pathMatch[1];
  }

  const fromCookie = getCookie("serverUuid");
  if (isValidUuid(fromCookie)) {
    return fromCookie;
  }

  try {
    if (window.parent && window.parent !== window) {
      const parentParams = new URLSearchParams(window.parent.location.search);
      const parentQuery = parentParams.get("serverUuid");
      if (isValidUuid(parentQuery)) {
        return parentQuery;
      }

      const parentMatch = window.parent.location.pathname.match(
        /\/server\/([a-f0-9-]+)/i
      );
      if (parentMatch?.[1]) {
        return parentMatch[1];
      }
    }
  } catch {
    // Cross-origin iframe
  }

  return null;
}

export function useServerUuid() {
  const serverUuid = computed(() => resolveServerUuid());
  return { serverUuid };
}
