import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnDestroy, OnInit, computed, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { appEnv } from './app-env';
import { PlayerState, TaptoearnApiService } from './taptoearn-api.service';

type SyncBatch = {
  tapCount: number;
  clientNonce: string;
  clientSeq: number;
};

@Component({
  selector: 'taptoearn-game',
  templateUrl: './taptoearn-game.html',
  styleUrl: './taptoearn-game.scss',
  standalone: true,
})
export class TapToEarnGameComponent implements OnInit, OnDestroy {
  private readonly pendingStorageKey = 'taptoearn_pending_taps';
  private readonly nextSeqStorageKey = 'taptoearn_next_client_seq';
  private readonly initDataStorageKey = 'taptoearn_init_data_cache';
  private readonly flushDebounceMs = 800;
  private readonly flushIntervalMs = 3000;
  private readonly immediateFlushThreshold = 20;
  private readonly maxLocalPendingQueue = 200;
  private readonly maxBatchSize = 50;

  protected readonly serverScore = signal(0);
  protected readonly pendingTaps = signal(0);
  protected readonly inFlightTaps = signal(0);
  protected readonly score = computed(
    () => this.serverScore() + this.pendingTaps() + this.inFlightTaps(),
  );
  protected readonly lastTap = signal<Date | null>(null);
  protected readonly telegramUserId = signal('');
  protected readonly isBootstrapping = signal(false);
  protected readonly isSyncing = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly requiresTelegram = signal(false);
  protected readonly telegramBotUrl = appEnv.telegramBotUrl;
  protected readonly telegramQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=${encodeURIComponent(
    appEnv.telegramBotUrl,
  )}`;

  private flushTimeoutId: ReturnType<typeof setTimeout> | null = null;
  private flushIntervalId: ReturnType<typeof setInterval> | null = null;
  private activeBatch: SyncBatch | null = null;
  private initData = '';
  private activeUserId: string | null = null;
  private readonly onVisibilityChange = (): void => {
    if (document.visibilityState === 'hidden') {
      void this.flushTaps();
    }
  };

  constructor(private readonly tapApi: TaptoearnApiService) { }

  ngOnInit(): void {
    this.startFlushInterval();
    document.addEventListener('visibilitychange', this.onVisibilityChange);
    window.addEventListener('beforeunload', this.onBeforeUnload);
    void this.bootstrapPlayer();
  }

  ngOnDestroy(): void {
    this.clearFlushTimer();
    this.stopFlushInterval();
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  }

  tap(): void {
    if (this.isBootstrapping() || this.activeUserId === null) {
      return;
    }

    if (this.pendingTaps() >= this.maxLocalPendingQueue) {
      this.error.set('Too many queued taps. Please wait for sync.');
      return;
    }

    this.pendingTaps.update((value) => value + 1);
    this.persistPendingTaps();
    this.lastTap.set(new Date());
    this.error.set(null);

    if (this.pendingTaps() >= this.immediateFlushThreshold) {
      void this.flushTaps();
      return;
    }

    this.scheduleFlush();
  }

  private async bootstrapPlayer(): Promise<void> {
    this.isBootstrapping.set(true);
    this.error.set(null);
    this.initData = this.resolveInitData();

    if (!this.initData) {
      this.requiresTelegram.set(true);
      this.error.set('Please open this game from Telegram Mini App.');
      this.isBootstrapping.set(false);
      return;
    }

    try {
      const response = await firstValueFrom(
        this.tapApi.syncPlayer({
          init_data: this.initData,
        }),
      );

      sessionStorage.setItem(this.initDataStorageKey, this.initData);
      this.applyPlayerState(response.player);
      this.switchActiveUser(response.player.telegram_user_id);
    } catch (error) {
      this.error.set(this.resolveSyncError(error));
    } finally {
      this.isBootstrapping.set(false);
    }
  }

  private async flushTaps(): Promise<void> {
    if (this.isSyncing() || this.isBootstrapping()) {
      return;
    }

    const batch = this.getOrCreateBatch();
    if (!batch) {
      return;
    }

    this.isSyncing.set(true);

    try {
      const response = await firstValueFrom(
        this.tapApi.tap({
          init_data: this.initData,
          tap_count: batch.tapCount,
          source: 'web-app',
          client_nonce: batch.clientNonce,
          client_seq: batch.clientSeq,
        }),
      );

      this.inFlightTaps.update((value) => value - batch.tapCount);
      this.activeBatch = null;
      this.incrementNextClientSeq(batch.clientSeq);
      this.applyPlayerState(response.player);
      this.switchActiveUser(response.player.telegram_user_id);
      this.error.set(null);
    } catch (error) {
      this.error.set(this.resolveSyncError(error));
    } finally {
      this.isSyncing.set(false);
    }
  }

  private getOrCreateBatch(): SyncBatch | null {
    if (this.activeBatch) {
      return this.activeBatch;
    }

    const batchSize = this.pendingTaps();
    if (batchSize <= 0) {
      return null;
    }

    const sendCount = Math.min(batchSize, this.maxBatchSize);

    this.pendingTaps.update((value) => value - sendCount);
    this.inFlightTaps.update((value) => value + sendCount);
    this.persistPendingTaps();

    this.activeBatch = {
      tapCount: sendCount,
      clientNonce: this.buildClientNonce(),
      clientSeq: this.readNextClientSeq(),
    };

    return this.activeBatch;
  }

  private applyPlayerState(player: PlayerState): void {
    // Keep monotonic score on the client to avoid visual rollbacks during background sync.
    this.serverScore.update((current) => Math.max(current, player.coin_balance));
    this.lastTap.set(player.last_tap_at ? new Date(player.last_tap_at) : this.lastTap());
  }

  private resolveInitData(): string {
    const telegram = (
      window as { Telegram?: { WebApp?: { initData?: string; ready?: () => void } } }
    ).Telegram;
    telegram?.WebApp?.ready?.();
    const telegramInitData = telegram?.WebApp?.initData?.trim() ?? '';

    if (this.isLikelyTelegramInitData(telegramInitData)) {
      this.requiresTelegram.set(false);
      return telegramInitData;
    }

    const cachedInitData = sessionStorage.getItem(this.initDataStorageKey)?.trim() ?? '';
    if (this.isLikelyTelegramInitData(cachedInitData)) {
      this.requiresTelegram.set(false);
      return cachedInitData;
    }

    const fromUrl = this.resolveInitDataFromUrl();
    if (this.isLikelyTelegramInitData(fromUrl)) {
      this.requiresTelegram.set(false);
      return fromUrl;
    }

    if (appEnv.isProduction) {
      return '';
    }

    const demoUserId = this.resolveLocalDemoUserId();
    return `debug:${demoUserId}`;
  }

  private resolveInitDataFromUrl(): string {
    const searchParams = new URLSearchParams(window.location.search);
    const hashParams = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const rawHash = window.location.hash.replace(/^#/, '').trim();
    const candidates = [
      searchParams.get('tgWebAppData')?.trim() ?? '',
      hashParams.get('tgWebAppData')?.trim() ?? '',
      rawHash,
    ];

    for (const candidate of candidates) {
      if (!candidate) {
        continue;
      }

      const normalized = this.normalizeInitDataCandidate(candidate);
      if (this.isLikelyTelegramInitData(normalized)) {
        return normalized;
      }
    }

    return '';
  }

  private normalizeInitDataCandidate(raw: string): string {
    let value = raw.trim();

    if (value.startsWith('tgWebAppData=')) {
      value = value.slice('tgWebAppData='.length);
    }

    try {
      const decoded = decodeURIComponent(value);
      if (decoded.includes('hash=') && decoded.includes('auth_date=')) {
        value = decoded;
      }
    } catch {
      // Keep original value if decode fails.
    }

    return value;
  }

  private isLikelyTelegramInitData(value: string): boolean {
    return value.includes('hash=') && value.includes('auth_date=');
  }

  private resolveLocalDemoUserId(): string {
    const urlValue = new URLSearchParams(window.location.search).get('tgUserId');
    const storedValue = localStorage.getItem('telegram_user_id');
    const fallbackValue = `demo_${Math.floor(Math.random() * 1_000_000)}`;
    const resolved = urlValue ?? storedValue ?? fallbackValue;
    localStorage.setItem('telegram_user_id', resolved);

    return resolved;
  }

  private switchActiveUser(telegramUserId: string): void {
    if (this.activeUserId === telegramUserId) {
      this.telegramUserId.set(telegramUserId);
      return;
    }

    if (this.activeUserId !== null) {
      this.persistPendingTaps(this.activeUserId, this.pendingTaps());
    }

    this.activeUserId = telegramUserId;
    this.telegramUserId.set(telegramUserId);
    this.pendingTaps.set(this.readPendingTaps(telegramUserId));
    this.inFlightTaps.set(0);
    this.activeBatch = null;
  }

  private readNextClientSeq(): number {
    const userId = this.activeUserId;
    if (!userId) {
      return 1;
    }

    const raw = localStorage.getItem(this.userScopedKey(this.nextSeqStorageKey, userId));
    const value = raw ? Number(raw) : 1;

    if (!Number.isFinite(value) || value < 1) {
      return 1;
    }

    return Math.floor(value);
  }

  private incrementNextClientSeq(currentSeq: number): void {
    const userId = this.activeUserId;
    if (!userId) {
      return;
    }

    localStorage.setItem(this.userScopedKey(this.nextSeqStorageKey, userId), String(currentSeq + 1));
  }

  private buildClientNonce(): string {
    return `${this.readNextClientSeq()}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
  }

  private scheduleFlush(): void {
    this.clearFlushTimer();
    this.flushTimeoutId = window.setTimeout(() => {
      void this.flushTaps();
    }, this.flushDebounceMs);
  }

  private clearFlushTimer(): void {
    if (this.flushTimeoutId !== null) {
      clearTimeout(this.flushTimeoutId);
      this.flushTimeoutId = null;
    }
  }

  private startFlushInterval(): void {
    this.flushIntervalId = window.setInterval(() => {
      void this.flushTaps();
    }, this.flushIntervalMs);
  }

  private stopFlushInterval(): void {
    if (this.flushIntervalId !== null) {
      clearInterval(this.flushIntervalId);
      this.flushIntervalId = null;
    }
  }

  private readPendingTaps(userId: string): number {
    const raw = localStorage.getItem(this.userScopedKey(this.pendingStorageKey, userId));
    const value = raw ? Number(raw) : 0;

    if (!Number.isFinite(value) || value < 0) {
      return 0;
    }

    return Math.floor(value);
  }

  private persistPendingTaps(userId?: string, value?: number): void {
    const resolvedUserId = userId ?? this.activeUserId;
    if (!resolvedUserId) {
      return;
    }

    const resolvedValue = Math.min(this.maxLocalPendingQueue, value ?? this.pendingTaps());
    localStorage.setItem(
      this.userScopedKey(this.pendingStorageKey, resolvedUserId),
      String(resolvedValue),
    );
  }

  private userScopedKey(baseKey: string, userId: string): string {
    return `${baseKey}:${userId}`;
  }

  private resolveSyncError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      const apiMessage = this.extractApiErrorMessage(error.error);
      if (apiMessage) {
        return apiMessage;
      }
    }

    return 'Sync failed. Taps are queued and will retry.';
  }

  private extractApiErrorMessage(body: unknown): string | null {
    if (!body || typeof body !== 'object') {
      return null;
    }

    const record = body as Record<string, unknown>;
    if (typeof record['message'] === 'string' && record['message'].trim() !== '') {
      return record['message'];
    }

    const errors = record['errors'];
    if (!errors || typeof errors !== 'object') {
      return null;
    }

    const firstKey = Object.keys(errors)[0];
    if (!firstKey) {
      return null;
    }

    const entries = (errors as Record<string, unknown>)[firstKey];
    if (Array.isArray(entries) && typeof entries[0] === 'string') {
      return entries[0];
    }

    return null;
  }

  private readonly onBeforeUnload = (): void => {
    void this.flushTaps();
  };
}
