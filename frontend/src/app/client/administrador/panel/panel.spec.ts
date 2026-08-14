import { TestBed } from '@angular/core/testing';
import { AdministradorPanel } from './panel';

describe('AdministradorPanel', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdministradorPanel],
    }).compileComponents();
  });

  it('should create the panel', () => {
    const fixture = TestBed.createComponent(AdministradorPanel);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
