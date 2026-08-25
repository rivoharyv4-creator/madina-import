import { ImgHTMLAttributes } from 'react';

export default function BrandLogo({className='',alt='Madina Import',...props}:ImgHTMLAttributes<HTMLImageElement>){
 return <span className={`relative inline-block ${className}`}>
  <img src="/brand-logo-transparent" alt={alt} className="absolute inset-0 size-full object-contain" {...props}/>
  <img
   src="/brand-logo-transparent"
   alt=""
   aria-hidden="true"
   className="pointer-events-none absolute inset-0 size-full object-contain"
   style={{clipPath:'inset(88% 0 0 0)',filter:'brightness(0) saturate(100%) invert(89%) sepia(7%) saturate(161%) hue-rotate(177deg) brightness(97%) contrast(89%)'}}
  />
 </span>;
}
