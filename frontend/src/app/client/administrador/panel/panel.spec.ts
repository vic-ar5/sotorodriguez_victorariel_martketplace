import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideRouter } from '@angular/router';
import { provideLocationMocks } from '@angular/common/testing';
import { AdministradorPanel } from './panel';

describe('AdministradorPanel', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdministradorPanel],
      providers: [
        provideHttpClient(),
        provideRouter([]),
        provideLocationMocks(),
      ],
    }).compileComponents();
  });

  it('should create the panel', () => {
    const fixture = TestBed.createComponent(AdministradorPanel);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
