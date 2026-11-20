import { Instagram, Mail, MessageCircle } from "lucide-react";
import { contact } from "../data.js";
import Icon from "./Icon.jsx";

export default function SocialLinks() {
  return (
    <>
      <a className="fo-icon-btn" href={contact.instagramUrl} target="_blank" rel="noopener noreferrer" aria-label="Instagram (opens in a new tab)">
        <Icon as={Instagram} />
      </a>
      <a className="fo-icon-btn" href={contact.whatsappUrl} target="_blank" rel="noopener noreferrer" aria-label="WhatsApp (opens in a new tab)">
        <Icon as={MessageCircle} />
      </a>
      <a className="fo-icon-btn" href={`mailto:${contact.email}`} aria-label={`Email ${contact.email}`}>
        <Icon as={Mail} />
      </a>
    </>
  );
}
