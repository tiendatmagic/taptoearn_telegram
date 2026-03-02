import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { resolveApiBaseUrl } from './app-env';

export interface PlayerState {
  id: number;
  telegram_user_id: string;
  username: string | null;
  first_name: string | null;
  last_name: string | null;
  total_taps: number;
  coin_balance: number;
  last_tap_at: string | null;
}

export interface SyncPlayerPayload {
  init_data: string;
}

export interface SyncPlayerResponse {
  player: PlayerState;
}

export interface TapPayload {
  init_data: string;
  tap_count?: number;
  source?: string;
  client_nonce: string;
  client_seq: number;
}

export interface TapResponse {
  coins_earned: number;
  player: PlayerState;
}

@Injectable({ providedIn: 'root' })
export class TaptoearnApiService {
  private readonly baseUrl = `${resolveApiBaseUrl()}/api/v1`;

  constructor(private readonly http: HttpClient) {}

  syncPlayer(payload: SyncPlayerPayload): Observable<SyncPlayerResponse> {
    return this.http.post<SyncPlayerResponse>(`${this.baseUrl}/players/sync`, payload);
  }

  tap(payload: TapPayload): Observable<TapResponse> {
    return this.http.post<TapResponse>(`${this.baseUrl}/tap`, payload);
  }
}
