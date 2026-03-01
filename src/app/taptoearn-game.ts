import { Component, signal } from '@angular/core';

@Component({
  selector: 'taptoearn-game',
  templateUrl: './taptoearn-game.html',
  styleUrl: './taptoearn-game.scss',
  standalone: true,
})
export class TapToEarnGameComponent {
  protected readonly score = signal(0);
  protected readonly earning = signal(0);
  protected readonly lastTap = signal<Date | null>(null);
  protected readonly earningPerTap = 1;

  tap() {
    this.score.update(v => v + 1);
    this.earning.update(v => v + this.earningPerTap);
    this.lastTap.set(new Date());
  }

  reset() {
    this.score.set(0);
    this.earning.set(0);
    this.lastTap.set(null);
  }
}
