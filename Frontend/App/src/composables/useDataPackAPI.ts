import { useServerUuid } from "./useServerUuid";

interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  error_message?: string;
}

export type PackType = "datapacks" | "resourcepacks" | "craftingtweaks";

export interface World {
  name: string;
  path: string;
}

export interface Pack {
  name: string;
  display?: string;
  version?: string;
  description?: string;
  incompatible?: string[];
  lastupdated?: number;
  video?: string;
}

export interface PackCategory {
  category?: string;
  name?: string;
  packs: Pack[];
}

export interface PacksResponse {
  versionName?: string;
  categories: PackCategory[];
}

export type SelectedPacks = Record<string, string[]>;

async function parseJson<T>(response: Response): Promise<ApiResponse<T>> {
  return response.json() as Promise<ApiResponse<T>>;
}

export function useDataPackAPI() {
  const { serverUuid } = useServerUuid();

  function baseUrl(path: string): string {
    const uuid = serverUuid.value;
    if (!uuid) {
      throw new Error("Server UUID is required");
    }
    return `/api/user/servers/${uuid}/addons/datapackinstaller${path}`;
  }

  async function getMcVersions(): Promise<{ versions: string[]; default: string }> {
    const response = await fetch(baseUrl("/versions"));
    const data = await parseJson<{ versions: string[]; default: string }>(response);
    if (data.success && Array.isArray(data.data?.versions)) {
      return {
        versions: data.data.versions,
        default: data.data.default ?? data.data.versions[0] ?? "26.1",
      };
    }
    throw new Error(data.message ?? "Failed to load Minecraft versions");
  }

  async function detectVersion(): Promise<string | null> {
    const response = await fetch(baseUrl("/detect-version"));
    const data = await parseJson<{ version: string | null }>(response);
    if (data.success) {
      return data.data?.version ?? null;
    }
    throw new Error(data.message ?? "Failed to detect version");
  }

  async function getWorlds(): Promise<World[]> {
    const response = await fetch(baseUrl("/worlds"));
    const data = await parseJson<{ worlds: World[] }>(response);
    if (data.success && Array.isArray(data.data?.worlds)) {
      return data.data.worlds;
    }
    throw new Error(data.message ?? "Failed to load worlds");
  }

  async function getPacks(
    mcVersion: string,
    type: PackType
  ): Promise<PacksResponse> {
    const query = new URLSearchParams({
      mcVersion,
      type,
    });
    const response = await fetch(baseUrl(`/packs?${query}`));
    const data = await parseJson<PacksResponse>(response);
    if (data.success && data.data?.categories) {
      return data.data;
    }
    throw new Error(data.message ?? data.error_message ?? "Failed to load packs");
  }

  function getPackImageUrl(
    packName: string,
    mcVersion: string,
    type: PackType
  ): string {
    const query = new URLSearchParams({
      pack: packName,
      mcVersion,
      type,
    });
    return `${baseUrl("/image")}?${query}`;
  }

  async function installPacks(
    mcVersion: string,
    packType: PackType,
    packs: SelectedPacks,
    world: string
  ): Promise<void> {
    const response = await fetch(baseUrl("/install"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mcVersion,
        pack_type: packType,
        packs,
        world,
      }),
    });
    const data = await parseJson(response);
    if (!data.success) {
      throw new Error(data.error_message ?? data.message ?? "Installation failed");
    }
  }

  return {
    getMcVersions,
    detectVersion,
    getWorlds,
    getPacks,
    getPackImageUrl,
    installPacks,
  };
}
