import { TestBed } from '@angular/core/testing';
import { CompradorPanel } from './panel';

describe('CompradorPanel', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CompradorPanel],
    }).compileComponents();
  });

  it('should create the panel', () => {
    const fixture = TestBed.createComponent(CompradorPanel);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
