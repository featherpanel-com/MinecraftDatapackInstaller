import { ref, watch, onMounted } from "vue";

/**
 * Composable for persisting tool state to localStorage
 * @param toolId - Unique identifier for the tool
 * @param defaultState - Default state object
 * @returns Reactive state and reset function
 */
export function useToolState<T extends Record<string, any>>(
  toolId: string,
  defaultState: T
) {
  const storageKey = `mcutils_${toolId}`;
  const state = ref<T>({ ...defaultState });

  // Load saved state on mount
  onMounted(() => {
    try {
      const saved = localStorage.getItem(storageKey);
      if (saved) {
        const parsed = JSON.parse(saved);
        // Merge saved state with defaults to handle new fields
        state.value = { ...defaultState, ...parsed };
      }
    } catch (error) {
      console.error(`Failed to load state for ${toolId}:`, error);
    }
  });

  // Save state to localStorage whenever it changes
  watch(
    state,
    (newState) => {
      try {
        localStorage.setItem(storageKey, JSON.stringify(newState));
      } catch (error) {
        console.error(`Failed to save state for ${toolId}:`, error);
      }
    },
    { deep: true }
  );

  // Reset to default state
  const resetToDefault = () => {
    state.value = { ...defaultState };
    localStorage.removeItem(storageKey);
  };

  return {
    state,
    resetToDefault,
  };
}
