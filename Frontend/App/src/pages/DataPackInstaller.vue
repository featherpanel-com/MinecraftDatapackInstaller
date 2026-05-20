<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertTriangle,
  Check,
  Download,
  ExternalLink,
  Info,
  Loader2,
  Package,
  Search,
  X,
} from "lucide-vue-next";
import { useToast } from "vue-toastification";
import {
  useDataPackAPI,
  type Pack,
  type PackCategory,
  type PackType,
  type SelectedPacks,
} from "@/composables/useDataPackAPI";

type PageView = "browse" | "installing";

const PACK_TYPE_LABELS: Record<PackType, string> = {
  datapacks: "Data Packs",
  resourcepacks: "Resource Packs",
  craftingtweaks: "Crafting Tweaks",
};

const toast = useToast();
const api = useDataPackAPI();

const pageView = ref<PageView>("browse");
const pageLoading = ref(true);
const pageError = ref<string | null>(null);
const packsLoading = ref(false);
const installSuccess = ref(false);

const mcVersions = ref<string[]>([]);
const detectedVersion = ref<string | null>(null);
const mcVersion = ref("26.1");
const packType = ref<PackType>("datapacks");
const worlds = ref<{ name: string; path: string }[]>([]);
const selectedWorld = ref("");
const searchQuery = ref("");

const packsData = ref<{ versionName?: string; categories: PackCategory[] } | null>(
  null
);
const selectedPacks = ref<SelectedPacks>({});
const failedImages = ref(new Set<string>());

const detailPack = ref<{ pack: Pack; categoryName: string } | null>(null);

const selectedCount = computed(() =>
  Object.values(selectedPacks.value).reduce((sum, list) => sum + list.length, 0)
);

const hasSelection = computed(() => selectedCount.value > 0);

const filteredCategories = computed(() => {
  if (!packsData.value?.categories) return [];
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return packsData.value.categories;

  return packsData.value.categories
    .map((category) => ({
      ...category,
      packs: category.packs.filter((pack) => {
        const name = (pack.display || pack.name).toLowerCase();
        const desc = (pack.description || "").toLowerCase();
        return name.includes(query) || desc.includes(query);
      }),
    }))
    .filter((category) => category.packs.length > 0);
});

function categoryName(category: PackCategory): string {
  return category.category || category.name || "Other";
}

function isPackSelected(category: string, packName: string): boolean {
  return selectedPacks.value[category]?.includes(packName) ?? false;
}

function togglePack(category: string, packName: string) {
  const current = { ...selectedPacks.value };
  if (!current[category]) {
    current[category] = [packName];
  } else if (current[category].includes(packName)) {
    current[category] = current[category].filter((p) => p !== packName);
    if (current[category].length === 0) {
      delete current[category];
    }
  } else {
    current[category] = [...current[category], packName];
  }
  selectedPacks.value = current;
}

function clearSelection() {
  selectedPacks.value = {};
}

function imageKey(category: string, packName: string): string {
  return `${category}-${packName}`;
}

function packImageUrl(packName: string): string {
  return api.getPackImageUrl(packName, mcVersion.value, packType.value);
}

function onImageError(category: string, packName: string) {
  failedImages.value.add(imageKey(category, packName));
}

async function loadPacks() {
  packsLoading.value = true;
  pageError.value = null;
  failedImages.value.clear();

  try {
    packsData.value = await api.getPacks(mcVersion.value, packType.value);
  } catch (err) {
    pageError.value = err instanceof Error ? err.message : "Failed to load packs";
    packsData.value = null;
  } finally {
    packsLoading.value = false;
  }
}

async function init() {
  pageLoading.value = true;
  pageError.value = null;

  try {
    const [versionInfo, version, worldList] = await Promise.all([
      api.getMcVersions(),
      api.detectVersion(),
      api.getWorlds(),
    ]);

    mcVersions.value = versionInfo.versions;
    mcVersion.value = versionInfo.default;

    detectedVersion.value = version;
    if (version && mcVersions.value.includes(version)) {
      mcVersion.value = version;
    }

    worlds.value = worldList;
    if (worldList.some((w) => w.name === "world")) {
      selectedWorld.value = "world";
    } else if (worldList.length > 0) {
      selectedWorld.value = worldList[0].name;
    }

    await loadPacks();
  } catch (err) {
    pageError.value = err instanceof Error ? err.message : "Failed to initialize";
  } finally {
    pageLoading.value = false;
  }
}

async function handleInstall() {
  if (!hasSelection.value) {
    toast.warning("Please select at least one pack");
    return;
  }
  if (!selectedWorld.value) {
    toast.warning("Please select a world");
    return;
  }

  pageView.value = "installing";
  installSuccess.value = false;
  pageError.value = null;

  try {
    await api.installPacks(
      mcVersion.value,
      packType.value,
      selectedPacks.value,
      selectedWorld.value
    );
    installSuccess.value = true;
    toast.success("Packs installed. Restart your server or run /reload to apply.");
    clearSelection();
    pageView.value = "browse";
  } catch (err) {
    pageError.value = err instanceof Error ? err.message : "Installation failed";
    toast.error(pageError.value);
    pageView.value = "browse";
  }
}

watch([mcVersion, packType], () => {
  if (pageLoading.value) return;
  clearSelection();
  loadPacks();
});

onMounted(init);
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <!-- Installing -->
      <template v-if="pageView === 'installing'">
        <div class="flex flex-col items-center justify-center py-24 gap-4">
          <Loader2 class="h-10 w-10 animate-spin text-muted-foreground" />
          <div class="text-center space-y-1">
            <p class="text-lg font-medium">Installing {{ selectedCount }} pack(s)</p>
            <p class="text-sm text-muted-foreground">
              Downloading from Vanilla Tweaks and uploading to {{ selectedWorld }}.
            </p>
          </div>
        </div>
      </template>

      <!-- Browse -->
      <template v-else>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 class="text-2xl font-semibold">Vanilla Tweaks Installer</h1>
            <p class="text-sm text-muted-foreground mt-1">
              Install {{ PACK_TYPE_LABELS[packType].toLowerCase() }} to your server
              <span v-if="packsData?.versionName"> · {{ packsData.versionName }}</span>
            </p>
          </div>
          <div v-if="hasSelection" class="flex gap-2 shrink-0">
            <Button variant="outline" size="sm" @click="clearSelection">
              <X class="h-4 w-4 mr-1" />
              Clear ({{ selectedCount }})
            </Button>
            <Button size="sm" :disabled="!selectedWorld" @click="handleInstall">
              <Download class="h-4 w-4 mr-1" />
              Install
            </Button>
          </div>
        </div>

        <div v-if="pageLoading" class="flex justify-center py-16">
          <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>

        <template v-else>
          <Alert v-if="pageError" variant="destructive" class="mb-4">
            <AlertTriangle class="h-4 w-4" />
            <AlertDescription>{{ pageError }}</AlertDescription>
          </Alert>

          <Alert v-if="installSuccess" class="mb-4">
            <Check class="h-4 w-4" />
            <AlertDescription class="text-sm">
              Packs installed successfully. Restart your server or run
              <code class="mx-1 rounded bg-muted px-1.5 py-0.5 text-xs">/reload</code>
              to apply changes.
            </AlertDescription>
          </Alert>

          <Card class="mb-6">
            <CardHeader>
              <CardTitle class="text-base">Settings</CardTitle>
              <CardDescription>Choose version, world, and pack type</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="space-y-2">
                  <Label>Version</Label>
                  <Select v-model="mcVersion">
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="v in mcVersions" :key="v" :value="v">
                        {{ v }}
                        <Badge
                          v-if="detectedVersion === v"
                          variant="secondary"
                          class="ml-2 h-4 text-xs"
                        >
                          Auto
                        </Badge>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="space-y-2">
                  <Label>World</Label>
                  <Select v-model="selectedWorld" :disabled="worlds.length === 0">
                    <SelectTrigger>
                      <SelectValue
                        :placeholder="worlds.length === 0 ? 'No worlds found' : 'Select world'"
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

                <div class="space-y-2 sm:col-span-2">
                  <Label>Search</Label>
                  <div class="relative">
                    <Search
                      class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                    />
                    <Input v-model="searchQuery" class="pl-9" placeholder="Search packs..." />
                  </div>
                </div>
              </div>

              <Tabs v-model="packType">
                <TabsList class="grid w-full grid-cols-3">
                  <TabsTrigger value="datapacks">Data Packs</TabsTrigger>
                  <TabsTrigger value="resourcepacks">Resource Packs</TabsTrigger>
                  <TabsTrigger value="craftingtweaks">Crafting Tweaks</TabsTrigger>
                </TabsList>
              </Tabs>
            </CardContent>
          </Card>

          <Alert v-if="worlds.length === 0" class="mb-4">
            <Info class="h-4 w-4" />
            <AlertDescription class="text-sm">
              No valid Minecraft worlds were found on this server. Packs install into a
              world's datapacks folder.
            </AlertDescription>
          </Alert>

          <div v-if="packsLoading" class="flex justify-center py-16">
            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
          </div>

          <div v-else-if="filteredCategories.length === 0" class="text-center py-16">
            <Search class="h-10 w-10 mx-auto mb-3 opacity-40 text-muted-foreground" />
            <p class="text-sm font-medium">No packs found</p>
            <p class="text-xs text-muted-foreground mt-1">
              {{
                searchQuery
                  ? "Try a different search term"
                  : "No packs available for this version"
              }}
            </p>
          </div>

          <div v-else class="space-y-6">
            <section
              v-for="category in filteredCategories"
              :key="categoryName(category)"
              class="space-y-3"
            >
              <div>
                <h2 class="text-sm font-medium">{{ categoryName(category) }}</h2>
                <p class="text-xs text-muted-foreground">
                  {{ category.packs.length }}
                  {{ category.packs.length === 1 ? "pack" : "packs" }}
                </p>
              </div>

              <div
                class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2"
              >
                <div
                  v-for="pack in category.packs"
                  :key="pack.name"
                  class="relative"
                >
                  <button
                    type="button"
                    class="w-full flex flex-col rounded-lg border p-2 text-left transition-colors"
                    :class="
                      isPackSelected(categoryName(category), pack.name)
                        ? 'border-primary bg-accent'
                        : 'border-border bg-card hover:bg-muted/50'
                    "
                    :title="pack.description || pack.display || pack.name"
                    @click="togglePack(categoryName(category), pack.name)"
                  >
                    <div
                      class="relative mb-2 aspect-square w-full overflow-hidden rounded bg-muted"
                    >
                      <img
                        v-if="!failedImages.has(imageKey(categoryName(category), pack.name))"
                        :src="packImageUrl(pack.name)"
                        :alt="pack.display || pack.name"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        @error="onImageError(categoryName(category), pack.name)"
                      />
                      <div
                        v-else
                        class="flex h-full w-full items-center justify-center"
                      >
                        <Package class="h-6 w-6 text-muted-foreground/50" />
                      </div>
                      <div
                        v-if="isPackSelected(categoryName(category), pack.name)"
                        class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary"
                      >
                        <Check class="h-2.5 w-2.5 text-primary-foreground" />
                      </div>
                      <div
                        v-if="pack.incompatible && pack.incompatible.length > 0"
                        class="absolute left-1 top-1"
                      >
                        <AlertTriangle class="h-3.5 w-3.5 text-yellow-500" />
                      </div>
                    </div>
                    <p
                      class="text-[11px] font-medium line-clamp-2 leading-tight"
                      :class="
                        isPackSelected(categoryName(category), pack.name)
                          ? 'text-primary'
                          : ''
                      "
                    >
                      {{ pack.display || pack.name }}
                    </p>
                    <p v-if="pack.version" class="text-[10px] text-muted-foreground">
                      v{{ pack.version }}
                    </p>
                  </button>

                  <button
                    v-if="pack.description || pack.video || pack.incompatible?.length"
                    type="button"
                    class="absolute -top-1 -right-1 z-10 flex h-5 w-5 items-center justify-center rounded-full border bg-background shadow-sm hover:bg-muted"
                    @click.stop="
                      detailPack = { pack, categoryName: categoryName(category) }
                    "
                  >
                    <Info class="h-3 w-3 text-muted-foreground" />
                  </button>
                </div>
              </div>
            </section>
          </div>
        </template>
      </template>

      <!-- Pack detail dialog -->
      <Dialog
        :open="detailPack !== null"
        @update:open="(open) => !open && (detailPack = null)"
      >
        <DialogContent v-if="detailPack" class="max-w-lg max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {{ detailPack.pack.display || detailPack.pack.name }}
            </DialogTitle>
            <DialogDescription v-if="detailPack.pack.version">
              Version {{ detailPack.pack.version }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4">
            <div class="mx-auto h-32 w-32 overflow-hidden rounded-lg bg-muted">
              <img
                v-if="!failedImages.has(imageKey(detailPack.categoryName, detailPack.pack.name))"
                :src="packImageUrl(detailPack.pack.name)"
                :alt="detailPack.pack.display || detailPack.pack.name"
                class="h-full w-full object-cover"
                @error="onImageError(detailPack.categoryName, detailPack.pack.name)"
              />
              <div v-else class="flex h-full w-full items-center justify-center">
                <Package class="h-10 w-10 text-muted-foreground/50" />
              </div>
            </div>

            <p
              v-if="detailPack.pack.description"
              class="text-sm text-muted-foreground leading-relaxed whitespace-pre-line"
            >
              {{ detailPack.pack.description }}
            </p>

            <Alert
              v-if="detailPack.pack.incompatible && detailPack.pack.incompatible.length > 0"
              variant="destructive"
            >
              <AlertTriangle class="h-4 w-4" />
              <AlertDescription class="text-sm">
                <p class="font-medium mb-1">Incompatible with:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                  <li v-for="item in detailPack.pack.incompatible" :key="item">
                    {{ item }}
                  </li>
                </ul>
              </AlertDescription>
            </Alert>

            <Button
              v-if="detailPack.pack.video"
              variant="outline"
              size="sm"
              as="a"
              :href="detailPack.pack.video"
              target="_blank"
              rel="noopener noreferrer"
            >
              <ExternalLink class="h-4 w-4 mr-2" />
              Watch video
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
