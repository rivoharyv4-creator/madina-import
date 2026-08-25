import BrandLogo from './BrandLogo';
import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props:ImgHTMLAttributes<HTMLImageElement>){
 return <BrandLogo {...props}/>;
}
