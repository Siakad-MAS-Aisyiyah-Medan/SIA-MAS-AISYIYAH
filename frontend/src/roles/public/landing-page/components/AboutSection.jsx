import { School, Quote } from 'lucide-react';
import AppLogo from '@/shared/components/AppLogo';
import { resolveStorageUrl } from '@/shared/services/apiHelpers';
import { apiConfig } from '@/config/api.config';

export default function AboutSection({ profil }) {
  const kepsekPhotoUrl = profil?.foto_kepsek ? resolveStorageUrl(profil.foto_kepsek, apiConfig) : undefined;

  return (
    <section id="profil" className="lp-section lp-section--soft">
      <div className="lp-container">
        <div className="lp-section-header">
          <h2 className="lp-section-title">Profil Sekolah</h2>
          <p className="lp-section-subtitle">
            {profil?.tentang_kami || profil?.hero_subtitle || 'MAS Aisyiyah Medan berkomitmen untuk memberikan pendidikan berkualitas berlandaskan nilai-nilai Islam dan berorientasi pada pengembangan potensi peserta didik secara menyeluruh.'}
          </p>
        </div>

        <div className="lp-about-grid" style={{ gridTemplateColumns: '1fr', maxWidth: '800px', margin: '0 auto' }}>
          <div className="lp-card lp-sambutan-card">
            <div className="lp-sambutan-card__photo">
              {kepsekPhotoUrl ? (
                <img 
                  src={kepsekPhotoUrl} 
                  onError={(event) => { event.currentTarget.onerror = null; event.currentTarget.src = '/logo-aisyiyah.jpg'; }}
                  alt="Kepala Sekolah" 
                  style={{ width: 80, height: 80, borderRadius: '50%', objectFit: 'cover' }} 
                />
              ) : (
                <AppLogo size={80} />
              )}
              <span>Kepala Sekolah</span>
            </div>
            <div>
              <h3>Kata Sambutan Kepala Sekolah</h3>
              <blockquote>
                {profil?.kata_sambutan || 'Dengan semangat Islam dan kecintaan terhadap ilmu pengetahuan, kami terus berupaya menciptakan lingkungan belajar yang kondusif dan menyenangkan bagi seluruh peserta didik. Bersama-sama kita wujudkan generasi unggul yang berakhlak mulia.'}
              </blockquote>
              <cite>{profil?.nama_kepsek || 'Kepala MAS Aisyiyah Medan'}</cite>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
