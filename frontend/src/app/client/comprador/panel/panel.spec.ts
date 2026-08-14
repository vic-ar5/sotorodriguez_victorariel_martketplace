import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { provideLocationMocks } from '@angular/common/testing';
import { CompradorPanel } from './panel';

describe('CompradorPanel', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CompradorPanel],
      providers: [
        provideHttpClient(),
        provideRouter([]),
        provideLocationMocks(),
      ],
    }).compileComponents();
  });

  it('should create the panel', () => {
    const fixture = TestBed.createComponent(CompradorPanel);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
