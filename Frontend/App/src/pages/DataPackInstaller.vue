<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Package,
  Download,
  Globe,
  AlertCircle,
  CheckCircle2,
  Loader2,
  Server,
  Search,
  X,
  Box,
  Info,
  ExternalLink,
  AlertTriangle,
} from "lucide-vue-next";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  useDatapackAPI,
  type PackData,
  type World,
  type Category,
  type Pack,
} from "@/composables/useDatapackAPI";
import { useServerUuid } from "@/composables/useServerUuid";

const { serverUuid } = useServerUuid();
const {
  loading,
  error: apiError,
  detectVersion,
  getWorlds,
  getPacks,
  getPackImageUrl,
  installPacks,
} = useDatapackAPI();

// State
const detectedVersion = ref<string | null>(null);
const selectedVersion = ref<string>("1.21");
const worlds = ref<World[]>([]);
const selectedWorld = ref<string>("world");
const packType = ref<"datapacks" | "resourcepacks" | "craftingtweaks">(
  "datapacks"
);
const packData = ref<PackData | null>(null);
const selectedPacks = ref<Record<string, string[]>>({});
const searchQuery = ref<string>("");
const installedSuccessfully = ref(false);
const installing = ref(false);
const error = ref<string | null>(null);
const imageErrors = ref<Set<string>>(new Set());
const selectedPackDialog = ref<{ pack: Pack; categoryName: string } | null>(
  null
);

// Available Minecraft versions (major versions only)
const versions = [
  "1.21",
  "1.20",
  "1.19",
  "1.18",
  "1.17",
  "1.16",
  "1.15",
  "1.14",
  "1.13",
];

// Computed
const hasSelectedPacks = computed(() => {
  return Object.values(selectedPacks.value).some((packs) => packs.length > 0);
});

const selectedPacksCount = computed(() => {
  return Object.values(selectedPacks.value).reduce(
    (total, packs) => total + packs.length,
    0
  );
});

const filteredCategories = computed(() => {
  if (!packData.value || !packData.value.categories) return [];
  if (!searchQuery.value.trim()) return packData.value.categories;

  const query = searchQuery.value.toLowerCase();
  return packData.value.categories
    .map((category) => {
      const filteredPacks = category.packs.filter((pack) => {
        const name = (pack.display || pack.name || "").toLowerCase();
        const description = (pack.description || "").toLowerCase();
        return name.includes(query) || description.includes(query);
      });
      return { ...category, packs: filteredPacks };
    })
    .filter((category) => category.packs.length > 0);
});

const packTypeLabels = {
  datapacks: "Data Packs",
  resourcepacks: "Resource Packs",
  craftingtweaks: "Crafting Tweaks",
};

// Methods
const initialize = async () => {
  if (!serverUuid.value) {
    error.value = "Server UUID is missing";
    return;
  }

  try {
    // Detect version
    const version = await detectVersion();
    if (version) {
      detectedVersion.value = version;
      selectedVersion.value = version;
    }

    // Get worlds
    const worldList = await getWorlds();
    worlds.value = worldList;
    if (worldList.length > 0 && worldList.some((w) => w.name === "world")) {
      selectedWorld.value = "world";
    } else if (worldList.length > 0) {
      selectedWorld.value = worldList[0]?.name || "";
    }

    // Load packs
    await loadPacks();
  } catch (err) {
    error.value = err instanceof Error ? err.message : "Failed to initialize";
  }
};

const loadPacks = async () => {
  if (!serverUuid.value) return;

  try {
    imageErrors.value.clear();
    const data = await getPacks(selectedVersion.value, packType.value);
    packData.value = data;
    selectedPacks.value = {};
  } catch (err) {
    error.value = err instanceof Error ? err.message : "Failed to load packs";
  }
};

const togglePack = (categoryName: string, packName: string) => {
  if (!selectedPacks.value[categoryName]) {
    selectedPacks.value[categoryName] = [];
  }

  const index = selectedPacks.value[categoryName].indexOf(packName);
  if (index > -1) {
    selectedPacks.value[categoryName].splice(index, 1);
    if (selectedPacks.value[categoryName].length === 0) {
      delete selectedPacks.value[categoryName];
    }
  } else {
    selectedPacks.value[categoryName].push(packName);
  }
};

const getCategoryName = (category: Category): string => {
  return category.category || category.name || "";
};

const isPackSelected = (categoryName: string, packName: string): boolean => {
  return selectedPacks.value[categoryName]?.includes(packName) || false;
};

const handleImageError = (packKey: string) => {
  imageErrors.value.add(packKey);
};

const isImageError = (packKey: string): boolean => {
  return imageErrors.value.has(packKey);
};

const handleInstall = async () => {
  if (!hasSelectedPacks.value) {
    error.value = "Please select at least one pack";
    return;
  }

  if (!selectedWorld.value) {
    error.value = "Please select a world";
    return;
  }

  installing.value = true;
  error.value = null;
  installedSuccessfully.value = false;

  try {
    await installPacks(
      selectedVersion.value,
      packType.value,
      selectedPacks.value,
      selectedWorld.value
    );
    installedSuccessfully.value = true;
    selectedPacks.value = {};

    // Reset success message after 5 seconds
    setTimeout(() => {
      installedSuccessfully.value = false;
    }, 5000);
  } catch (err) {
    error.value =
      err instanceof Error ? err.message : "Failed to install packs";
  } finally {
    installing.value = false;
  }
};

const clearSelection = () => {
  selectedPacks.value = {};
};

// Watch for changes
watch([selectedVersion, packType], () => {
  loadPacks();
  clearSelection();
});

// Lifecycle
onMounted(() => {
  initialize();
});
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Compact Header -->
    <div
      class="sticky top-0 z-20 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
    >
      <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <div
          class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <!-- Title Section -->
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
            >
              <Package class="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 class="text-xl font-bold text-foreground">
                Vanilla Tweaks Installer
              </h1>
              <p class="text-xs text-muted-foreground">
                Install {{ packTypeLabels[packType] }} to your server
                <span v-if="packData?.versionName" class="ml-1">
                  • {{ packData.versionName }}
                </span>
              </p>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="flex items-center gap-2">
            <Button
              v-if="hasSelectedPacks"
              variant="outline"
              size="sm"
              @click="clearSelection"
              :disabled="installing"
            >
              <X class="h-3.5 w-3.5 mr-1.5" />
              Clear ({{ selectedPacksCount }})
            </Button>
            <Button
              v-if="hasSelectedPacks"
              size="sm"
              @click="handleInstall"
              :disabled="installing || !selectedWorld"
            >
              <Loader2
                v-if="installing"
                class="h-3.5 w-3.5 mr-1.5 animate-spin"
              />
              <Download v-else class="h-3.5 w-3.5 mr-1.5" />
              {{ installing ? "Installing..." : "Install" }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <!-- Filters Bar -->
      <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <!-- Minecraft Version -->
        <div class="space-y-1.5">
          <label
            class="text-xs font-medium text-muted-foreground flex items-center gap-1.5"
          >
            <Server class="h-3 w-3" />
            Version
          </label>
          <Select v-model="selectedVersion">
            <SelectTrigger class="h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="version in versions"
                :key="version"
                :value="version"
              >
                <span class="flex items-center gap-2">
                  {{ version }}
                  <Badge
                    v-if="detectedVersion === version"
                    variant="secondary"
                    class="h-4 text-xs"
                  >
                    Auto
                  </Badge>
                </span>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- World Selection -->
        <div class="space-y-1.5">
          <label
            class="text-xs font-medium text-muted-foreground flex items-center gap-1.5"
          >
            <Globe class="h-3 w-3" />
            World
          </label>
          <Select v-model="selectedWorld" :disabled="worlds.length === 0">
            <SelectTrigger class="h-9">
              <SelectValue
                :placeholder="
                  worlds.length === 0 ? 'Loading...' : 'Select world'
                "
              />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="world in worlds"
                :key="world.name"
                :value="world.name"
              >
                {{ world.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Pack Type -->
        <div class="space-y-1.5">
          <label
            class="text-xs font-medium text-muted-foreground flex items-center gap-1.5"
          >
            <Box class="h-3 w-3" />
            Type
          </label>
          <Tabs v-model="packType" class="w-full">
            <TabsList class="grid w-full grid-cols-3 h-9">
              <TabsTrigger value="datapacks" class="text-xs">Data</TabsTrigger>
              <TabsTrigger value="resourcepacks" class="text-xs"
                >Resource</TabsTrigger
              >
              <TabsTrigger value="craftingtweaks" class="text-xs"
                >Crafting</TabsTrigger
              >
            </TabsList>
          </Tabs>
        </div>

        <!-- Search -->
        <div class="space-y-1.5">
          <label
            class="text-xs font-medium text-muted-foreground flex items-center gap-1.5"
          >
            <Search class="h-3 w-3" />
            Search
          </label>
          <div class="relative">
            <Search
              class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
            />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search packs..."
              class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-8 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 hover:bg-muted"
            >
              <X class="h-3.5 w-3.5 text-muted-foreground" />
            </button>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div class="mb-6 space-y-2">
        <Alert v-if="error || apiError" variant="destructive">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription class="text-sm">{{
            error || apiError
          }}</AlertDescription>
        </Alert>

        <Alert
          v-if="installedSuccessfully"
          class="border-green-500/50 bg-green-500/10"
        >
          <CheckCircle2 class="h-4 w-4 text-green-500" />
          <AlertDescription class="text-sm text-green-400">
            Packs installed successfully! Restart your server or run
            <code class="mx-1 rounded bg-background/50 px-1.5 py-0.5 text-xs"
              >/reload</code
            >
            to apply changes.
          </AlertDescription>
        </Alert>
      </div>

      <!-- Loading State -->
      <div
        v-if="loading"
        class="flex flex-col items-center justify-center py-16"
      >
        <Loader2 class="h-8 w-8 animate-spin text-primary mb-3" />
        <p class="text-sm text-muted-foreground">Loading packs...</p>
      </div>

      <!-- Pack Categories -->
      <div
        v-else-if="packData && filteredCategories.length > 0"
        class="space-y-4"
      >
        <div
          v-for="category in filteredCategories"
          :key="category.name"
          class="space-y-3"
        >
          <!-- Category Header -->
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-foreground">
                {{ category.category || category.name }}
              </h2>
              <p class="text-xs text-muted-foreground">
                {{ category.packs.length }} pack{{
                  category.packs.length !== 1 ? "s" : ""
                }}
                available
              </p>
            </div>
          </div>

          <!-- Pack Grid -->
          <div
            class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-8 gap-2.5"
          >
            <div
              v-for="pack in category.packs"
              :key="pack.name"
              class="group relative"
            >
              <button
                :class="
                  'relative flex flex-col rounded-lg border-2 p-2 transition-all duration-150 w-full ' +
                  (isPackSelected(getCategoryName(category), pack.name)
                    ? 'border-primary bg-primary/10 shadow-sm scale-[1.02]'
                    : 'border-border bg-card hover:border-primary/50 hover:shadow-sm hover:scale-[1.01] active:scale-[0.99]')
                "
                @click="togglePack(getCategoryName(category), pack.name)"
                :title="pack.description || pack.display || pack.name"
              >
                <!-- Pack Image Container -->
                <div
                  class="relative mb-1.5 aspect-square w-full overflow-hidden rounded bg-muted/30"
                >
                  <!-- Image -->
                  <img
                    v-if="
                      !isImageError(
                        `${getCategoryName(category)}-${pack.name}`
                      ) && getPackImageUrl(pack.name, selectedVersion, packType)
                    "
                    :src="
                      getPackImageUrl(pack.name, selectedVersion, packType) ||
                      ''
                    "
                    :alt="pack.display || pack.name"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                    @error="
                      handleImageError(
                        `${getCategoryName(category)}-${pack.name}`
                      )
                    "
                    loading="lazy"
                  />

                  <!-- Placeholder -->
                  <div
                    v-else
                    class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-muted/50 to-muted/30"
                  >
                    <div
                      class="mb-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary/10"
                    >
                      <Package class="h-3.5 w-3.5 text-primary/60" />
                    </div>
                  </div>

                  <!-- Selected Badge -->
                  <div
                    v-if="isPackSelected(getCategoryName(category), pack.name)"
                    class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary shadow-sm"
                  >
                    <CheckCircle2 class="h-2.5 w-2.5 text-primary-foreground" />
                  </div>

                  <!-- Incompatible Warning -->
                  <div
                    v-if="pack.incompatible && pack.incompatible.length > 0"
                    class="absolute left-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500/20 backdrop-blur-sm"
                  >
                    <AlertTriangle class="h-2.5 w-2.5 text-orange-500" />
                  </div>
                </div>

                <!-- Pack Name & Info -->
                <div class="space-y-0.5">
                  <h3
                    :class="
                      'line-clamp-2 text-left text-[10px] font-medium leading-tight ' +
                      (isPackSelected(getCategoryName(category), pack.name)
                        ? 'text-primary'
                        : 'text-foreground')
                    "
                  >
                    {{ pack.display || pack.name }}
                  </h3>
                  <div v-if="pack.version" class="flex items-center gap-1">
                    <span class="text-[9px] text-muted-foreground"
                      >v{{ pack.version }}</span
                    >
                  </div>
                </div>
              </button>

              <!-- Info Button -->
              <button
                v-if="
                  pack.description ||
                  pack.video ||
                  (pack.incompatible && pack.incompatible.length > 0)
                "
                @click.stop="
                  selectedPackDialog = {
                    pack,
                    categoryName: getCategoryName(category),
                  }
                "
                class="absolute -top-1 -right-1 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-background/80 border border-border shadow-sm hover:bg-background transition-colors"
                :title="pack.description || 'View details'"
              >
                <Info
                  class="h-3 w-3 text-muted-foreground hover:text-foreground"
                />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="packData && filteredCategories.length === 0"
        class="flex flex-col items-center justify-center py-16"
      >
        <div
          class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted"
        >
          <Search class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="mb-1 text-lg font-semibold">No packs found</h3>
        <p class="text-sm text-muted-foreground">
          {{
            searchQuery
              ? "Try a different search term"
              : "No packs available for this version"
          }}
        </p>
      </div>
    </div>

    <!-- Floating Install Button (Mobile) -->
    <div v-if="hasSelectedPacks" class="fixed bottom-4 right-4 z-30 sm:hidden">
      <Button
        size="lg"
        class="h-12 w-12 rounded-full shadow-lg"
        @click="handleInstall"
        :disabled="installing || !selectedWorld"
      >
        <Loader2 v-if="installing" class="h-5 w-5 animate-spin" />
        <Download v-else class="h-5 w-5" />
      </Button>
      <Badge
        class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-background p-0 text-xs"
      >
        {{ selectedPacksCount }}
      </Badge>
    </div>

    <!-- Pack Details Dialog -->
    <Dialog
      :open="selectedPackDialog !== null"
      @update:open="
        (open) => {
          if (!open) selectedPackDialog = null;
        }
      "
    >
      <DialogContent
        v-if="selectedPackDialog"
        class="max-w-2xl max-h-[90vh] overflow-y-auto"
      >
        <DialogHeader>
          <DialogTitle class="text-xl">
            {{
              selectedPackDialog.pack.display || selectedPackDialog.pack.name
            }}
          </DialogTitle>
          <DialogDescription v-if="selectedPackDialog.pack.version">
            Version {{ selectedPackDialog.pack.version }}
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <!-- Pack Image -->
          <div class="flex justify-center">
            <div
              class="relative h-32 w-32 overflow-hidden rounded-lg bg-muted/30"
            >
              <img
                v-if="
                  !isImageError(
                    `${selectedPackDialog.categoryName}-${selectedPackDialog.pack.name}`
                  ) &&
                  getPackImageUrl(
                    selectedPackDialog.pack.name,
                    selectedVersion,
                    packType
                  )
                "
                :src="
                  getPackImageUrl(
                    selectedPackDialog.pack.name,
                    selectedVersion,
                    packType
                  ) || ''
                "
                :alt="
                  selectedPackDialog.pack.display ||
                  selectedPackDialog.pack.name
                "
                class="h-full w-full object-cover"
                @error="
                  handleImageError(
                    `${selectedPackDialog.categoryName}-${selectedPackDialog.pack.name}`
                  )
                "
              />
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-muted/50 to-muted/30"
              >
                <Package class="h-12 w-12 text-muted-foreground/50" />
              </div>
            </div>
          </div>

          <!-- Description -->
          <div v-if="selectedPackDialog.pack.description">
            <h3 class="text-sm font-semibold mb-2">Description</h3>
            <p
              class="text-sm text-muted-foreground leading-relaxed whitespace-pre-line"
            >
              {{ selectedPackDialog.pack.description }}
            </p>
          </div>

          <!-- Incompatible Packs -->
          <div
            v-if="
              selectedPackDialog.pack.incompatible &&
              selectedPackDialog.pack.incompatible.length > 0
            "
            class="rounded-lg border border-orange-500/50 bg-orange-500/10 p-4"
          >
            <div class="flex items-center gap-2 mb-2">
              <AlertTriangle class="h-4 w-4 text-orange-500" />
              <h3 class="text-sm font-semibold text-orange-500">
                Incompatible Packs
              </h3>
            </div>
            <ul class="text-sm text-muted-foreground space-y-1">
              <li
                v-for="incompatible in selectedPackDialog.pack.incompatible"
                :key="incompatible"
              >
                • {{ incompatible }}
              </li>
            </ul>
          </div>

          <!-- Video Link -->
          <div
            v-if="selectedPackDialog.pack.video"
            class="flex items-center gap-2"
          >
            <a
              :href="selectedPackDialog.pack.video"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 text-sm text-primary hover:underline"
            >
              <ExternalLink class="h-4 w-4" />
              Watch demonstration video
            </a>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
