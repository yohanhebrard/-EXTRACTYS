import os
import requests
import json
import unidecode
import sys
import random
import re  # Ajout de re pour les expressions régulières
from datetime import datetime
from fpdf import FPDF
import os
import unidecode

# Importer les classes de design et ReportLab avec les imports manquants
import os
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib.styles import (
    getSampleStyleSheet,
    ParagraphStyle,
)  # Ajout de ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    Image,
)
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from DesignOffre import DesignOffre
from LayoutHandler import LayoutHandler


class GenerateurOffre:
    def __init__(self, json_path):
        self.json_path = json_path
        self.current_dir = os.path.dirname(os.path.abspath(__file__))
        self.pdf_output_dir = os.path.join(self.current_dir, "generated_offers")

        # S'assurer que le dossier de sortie existe
        os.makedirs(self.pdf_output_dir, exist_ok=True)

        # Initialisation du design et du layout
        self.design = DesignOffre()
        self.layout = LayoutHandler(self.design)

        # Informations de l'entreprise (pour les PDFs)
        self.company_info = {
            "company_name": "Votre Entreprise",
            "phone": "01 23 45 67 89",
            "email": "contact@entreprise.fr",
            "website": "www.entreprise.fr",
            "address": "123 Avenue de l'Exemple, 75000 Paris",
        }

        # Tenter de télécharger les polices au démarrage
        self.telecharger_polices_roboto()

    def log_debug(self, message):
        """Affiche un message de débogage"""
        print(json.dumps({"debug": str(message)}))

    def format_currency(self, amount):
        """Formate un montant en euros avec séparateur de milliers"""
        return self.design.format_currency(amount)

    def get_date_format(self):
        """Retourne la date formatée en français"""
        return self.design.get_date_format()

    def telecharger_polices_roboto(self):
        """Télécharge les polices Roboto si elles n'existent pas"""
        try:
            # S'assurer que le répertoire fonts existe
            fonts_dir = os.path.join(self.current_dir, "fonts")
            os.makedirs(fonts_dir, exist_ok=True)
            self.log_debug(f"Vérification du répertoire des polices: {fonts_dir}")

            # Liste des polices à télécharger
            polices_urls = {
                "Roboto-Regular.ttf": "https://github.com/googlefonts/roboto/raw/main/src/hinted/Roboto-Regular.ttf",
                "Roboto-Bold.ttf": "https://github.com/googlefonts/roboto/raw/main/src/hinted/Roboto-Bold.ttf",
                "Roboto-Italic.ttf": "https://github.com/googlefonts/roboto/raw/main/src/hinted/Roboto-Italic.ttf",
            }

            # Télécharger chaque police manquante
            for police_nom, police_url in polices_urls.items():
                police_path = os.path.join(fonts_dir, police_nom)
                if not os.path.exists(police_path):
                    self.log_debug(f"Téléchargement de la police {police_nom}...")
                    try:
                        response = requests.get(police_url, timeout=10)
                        if response.status_code == 200:
                            with open(police_path, "wb") as f:
                                f.write(response.content)
                            self.log_debug(
                                f"Police {police_nom} téléchargée avec succès"
                            )
                        else:
                            self.log_debug(
                                f"Échec du téléchargement de {police_nom}: HTTP {response.status_code}"
                            )
                    except Exception as e:
                        self.log_debug(
                            f"Erreur lors du téléchargement de {police_nom}: {str(e)}"
                        )

            # Vérifier si les polices ont été téléchargées
            polices_disponibles = all(
                os.path.exists(os.path.join(fonts_dir, police))
                for police in polices_urls.keys()
            )
            self.log_debug(f"Polices Roboto disponibles: {polices_disponibles}")
            return polices_disponibles

        except Exception as e:
            self.log_debug(
                f"Erreur lors de la vérification/téléchargement des polices: {str(e)}"
            )
            return False

    def generer_arguments_ollama(
        self,
        montant_actuel,
        economie_mensuelle,
        economie_annuelle,
        prestataire,
        conditions="",
    ):
        """Génère des arguments de vente avec Llama3 via Ollama"""
        try:
            # Vérifier d'abord si Ollama est disponible avec un timeout très court
            try:
                health_check = requests.get(
                    "http://localhost:11434/api/version", timeout=2
                )
                if health_check.status_code != 200:
                    self.log_debug(
                        "Ollama n'est pas disponible (échec du test de connexion)"
                    )
                    return self.arguments_par_defaut(
                        montant_actuel, economie_mensuelle, economie_annuelle
                    )
            except Exception as conn_err:
                self.log_debug(f"Ollama n'est pas disponible: {str(conn_err)}")
                return self.arguments_par_defaut(
                    montant_actuel, economie_mensuelle, economie_annuelle
                )

            # Simplifier le prompt pour éviter les confusions et instructions trop complexes
            pourcentage = round(
                (float(economie_mensuelle) / float(montant_actuel)) * 100, 1
            )
            conditions_text = conditions if conditions else "3 mois d'engagement"

            prompt = f"""Génère 3 arguments de vente en français pour une offre qui permet au client de faire des économies.

INFORMATIONS:
- Client: {prestataire}
- Montant actuel: {montant_actuel} EUR/mois
- Économie: {economie_mensuelle} EUR/mois ({pourcentage}%)
- Économie annuelle: {economie_annuelle} EUR
- Conditions: {conditions_text}

CONSIGNES:
- Sois clair, direct et percutant
- Maximum 2 phrases par argument
- Évite les phrases trop longues
- Format: 1. 2. 3. (avec numéros)

RÉPONSE:"""

            # Essayer de générer les arguments avec un timeout plus court
            response = requests.post(
                "http://localhost:11434/api/generate",
                json={
                    "model": "llama3",
                    "prompt": prompt,
                    "stream": False,
                    "temperature": 0.5,
                    "max_tokens": 250,
                    "system": "Tu es un expert marketing qui rédige des arguments de vente concis.",
                },
                timeout=5000,  # Réduire le timeout pour éviter d'attendre trop longtemps
            )

            # Vérifier que la réponse est valide
            if response.status_code == 200:
                result = response.json()
                arguments = self.nettoyer_arguments(result.get("response", ""))

                # Vérifier que nous avons 3 arguments valides
                if arguments and len(arguments) == 3:
                    return arguments

            # Si nous n'avons pas pu générer les arguments, utiliser les arguments par défaut
            self.log_debug(
                "Génération avec Ollama incomplète, utilisation des arguments par défaut"
            )

        except Exception as e:
            self.log_debug(f"Erreur lors de la génération des arguments: {str(e)}")

        return self.arguments_par_defaut(
            montant_actuel, economie_mensuelle, economie_annuelle
        )

    def nettoyer_arguments(self, texte):
        """Extraction robuste des arguments générés par Ollama"""
        self.log_debug(f"Texte brut reçu: {texte}")

        # Liste pour stocker les arguments extraits
        arguments = []

        # Méthode 1: Extraction par regex des arguments numérotés
        pattern = r"(\d+[\.\)])\s*([^\d].*?)(?=\s*\d+[\.\)]|$)"
        matches = re.findall(pattern, texte, re.DOTALL)

        if matches and len(matches) >= 1:
            self.log_debug(
                f"Extraction par numérotation: {len(matches)} arguments trouvés"
            )
            for _, content in matches:
                # Nettoyer l'argument
                arg = content.strip()
                if arg:
                    arguments.append(arg)

        # Si la première méthode échoue, essayer l'extraction par lignes
        if len(arguments) < 3:
            self.log_debug("Tentative d'extraction par lignes")
            lines = [line.strip() for line in texte.split("\n") if line.strip()]
            processed_lines = []

            for line in lines:
                # Ignorer les lignes d'introduction
                if re.search(r"voici|arguments|vente|conclusion", line, re.IGNORECASE):
                    continue

                # Supprimer la numérotation si présente
                clean_line = re.sub(r"^\d+[\.\)]-?\s*", "", line)

                # Ignorer les lignes très courtes qui pourraient être des artefacts
                if len(clean_line) > 15:  # Une longueur minimale raisonnable
                    processed_lines.append(clean_line)

            # Si nous avons des lignes valides, utilisons-les
            if processed_lines:
                arguments = processed_lines

        # Vérification finale et post-traitement
        if arguments and len(arguments) >= 1:
            # Ne garder que 3 arguments maximum
            arguments = arguments[:3]

            # Formater les arguments pour qu'ils soient présentables
            clean_arguments = []
            for arg in arguments:
                # Supprimer les caractères spéciaux problématiques pour FPDF
                clean_arg = arg.replace("€", "EUR").replace("«", '"').replace("»", '"')
                clean_arg = clean_arg.strip()

                # S'assurer que l'argument commence par une majuscule
                if clean_arg and len(clean_arg) > 1:
                    clean_arg = clean_arg[0].upper() + clean_arg[1:]

                # S'assurer qu'il se termine par un point
                if not clean_arg.endswith((".", "!", "?")):
                    clean_arg += "."

                clean_arguments.append(clean_arg)

            self.log_debug(f"Arguments nettoyés: {clean_arguments}")
            return clean_arguments

        # En dernier recours, utiliser des arguments par défaut
        self.log_debug(
            "Échec de l'extraction des arguments, utilisation des arguments par défaut"
        )
        return self.arguments_par_defaut(0, 0, 0)

    def arguments_par_defaut(
        self, montant_actuel, economie_mensuelle, economie_annuelle
    ):
        """Génère des arguments par défaut si Llama3 échoue"""
        pourcentage = int((float(economie_mensuelle) / float(montant_actuel)) * 100)
        return [
            f"Économisez {self.format_currency(economie_mensuelle)} chaque mois, soit {self.format_currency(economie_annuelle)} sur votre budget annuel, tout en bénéficiant d'un service de qualité supérieure.",
            f"Bénéficiez de services équivalents ou supérieurs en réduisant vos dépenses de {pourcentage}% par rapport à votre solution actuelle.",
            "Profitez d'une transition simple et rapide avec notre accompagnement personnalisé, sans interruption de service ni compromis sur les fonctionnalités.",
        ]

    class PDF(FPDF):
        def header(self):
            """En-tête du document FPDF (utilisé uniquement pour les versions simples)"""
            try:
                self.set_font("Arial", style="B", size=16)
                self.cell(200, 10, "Offre Commerciale", ln=True, align="C")
            except Exception as e:
                # Éviter que les erreurs de l'en-tête ne cassent tout
                pass

    def generer_pdf(self, data):
        try:
            safe_name = unidecode.unidecode(data["prestataire"]).replace(" ", "_")
            pdf_filename = os.path.join(
                self.pdf_output_dir,
                f"offre_{safe_name}_{datetime.now().strftime('%Y%m%d')}.pdf",
            )
            doc = SimpleDocTemplate(
                pdf_filename,
                pagesize=A4,
                leftMargin=25 * mm,
                rightMargin=25 * mm,
                topMargin=20 * mm,
                bottomMargin=25 * mm,
            )
            story = []

            # --- EN-TÊTE AVEC LOGO ET INFOS SOCIÉTÉ ---
            logo_path = os.path.join(self.current_dir, "../assets/img/extractys.png")
            if os.path.exists(logo_path):
                img = Image(logo_path, width=60, height=60)
                img.hAlign = "LEFT"
                story.append(img)
            story.append(Spacer(1, 2 * mm))

            header_style = ParagraphStyle(
                name="Header",
                fontName="Helvetica-Bold",
                fontSize=16,
                textColor=colors.HexColor("#003366"),
                alignment=0,
            )
            story.append(Paragraph("OFFRE COMMERCIALE", header_style))
            story.append(Spacer(1, 2 * mm))

            info_style = ParagraphStyle(
                name="Info",
                fontName="Helvetica",
                fontSize=10,
                textColor=colors.HexColor("#666"),
                alignment=0,
            )
            info_text = f"""
            <b>Date :</b> {datetime.now().strftime('%d/%m/%Y')}<br/>
            <b>Client :</b> {data['prestataire']}<br/>
            <b>Contact :</b> {self.company_info['phone']} | {self.company_info['email']}<br/>
            <b>Adresse :</b> {self.company_info['address']}
            """
            story.append(Paragraph(info_text, info_style))
            story.append(Spacer(1, 8 * mm))

            # --- INTRODUCTION ---
            intro_style = ParagraphStyle(
                name="Intro",
                fontName="Helvetica",
                fontSize=12,
                textColor=colors.HexColor("#222"),
                alignment=0,
                spaceAfter=10,
            )
            intro = (
                "Nous avons le plaisir de vous soumettre notre meilleure proposition, "
                "conçue pour répondre à vos besoins tout en optimisant vos coûts."
            )
            story.append(Paragraph(intro, intro_style))
            story.append(Spacer(1, 6 * mm))

            # --- TABLEAU D'ANALYSE ÉCONOMIQUE ---
            story.append(Paragraph("Analyse économique", header_style))
            pourcentage = round(
                (data["economie_mensuelle"] / data["montant_ttc"]) * 100, 1
            )
            data_table = [
                [
                    "Coût actuel",
                    f"{self.format_currency(data['montant_ttc'])} € / mois",
                ],
                [
                    "Économie mensuelle",
                    f"{self.format_currency(data['economie_mensuelle'])} €",
                ],
                [
                    "Économie annuelle",
                    f"{self.format_currency(data['economie_annuelle'])} €",
                ],
                ["% d'économie", f"{pourcentage}%"],
            ]
            table = Table(data_table, colWidths=[180, 120])
            table.setStyle(
                TableStyle(
                    [
                        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#003366")),
                        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                        ("BACKGROUND", (0, 1), (-1, -1), colors.HexColor("#F5F9FD")),
                        ("GRID", (0, 0), (-1, -1), 0.5, colors.HexColor("#003366")),
                        ("FONTSIZE", (0, 0), (-1, -1), 11),
                        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
                    ]
                )
            )
            story.append(table)
            story.append(Spacer(1, 10 * mm))

            # --- ARGUMENTS COMMERCIAUX ---
            story.append(Paragraph("Pourquoi choisir notre offre ?", header_style))
            arguments = self.generer_arguments_ollama(
                data["montant_ttc"],
                data["economie_mensuelle"],
                data["economie_annuelle"],
                data["prestataire"],
                data.get("conditions", ""),
            )
            bullet_style = ParagraphStyle(
                name="Bullet",
                fontName="Helvetica",
                fontSize=12,
                leftIndent=15,
                bulletIndent=0,
                spaceAfter=6,
                textColor=colors.HexColor("#003366"),
            )
            for arg in arguments:
                story.append(Paragraph(f"• {arg}", bullet_style))
            story.append(Spacer(1, 8 * mm))

            # --- APPEL À L'ACTION ---
            cta_style = ParagraphStyle(
                name="CTA",
                fontName="Helvetica-Bold",
                fontSize=13,
                alignment=1,
                textColor=colors.HexColor("#fff"),
                backColor=colors.HexColor("#0066CC"),
                spaceBefore=10,
                spaceAfter=10,
                borderPadding=(8, 8, 8, 8),
            )
            cta = "Contactez-nous dès aujourd'hui pour bénéficier de cette offre exclusive !"
            story.append(Paragraph(cta, cta_style))
            story.append(Spacer(1, 10 * mm))

            # --- SIGNATURE ---
            sign_style = ParagraphStyle(
                name="Sign",
                fontName="Helvetica",
                fontSize=11,
                alignment=0,
                textColor=colors.HexColor("#222"),
            )
            sign = (
                "Signature client : ___________________________<br/><br/>"
                "Signature société : ___________________________"
            )
            story.append(Paragraph(sign, sign_style))

            doc.build(story)
            self.log_debug(f"PDF généré avec succès: {pdf_filename}")
            return pdf_filename

        except Exception as e:
            self.log_debug(f"Erreur détaillée dans generer_pdf: {str(e)}")
            self.log_debug(f"Type d'erreur: {type(e).__name__}")
            return None

    def generer_pdf_secours(self, data):
        """Génère un PDF simple en cas d'échec de la version principale"""
        try:
            # Nom du fichier pour la version de secours
            safe_name = unidecode.unidecode(data["prestataire"]).replace(" ", "_")
            pdf_filename = os.path.join(
                self.pdf_output_dir,
                f"offre_secours_{safe_name}_{datetime.now().strftime('%Y%m%d')}.pdf",
            )

            self.log_debug(f"Génération du PDF de secours: {pdf_filename}")

            # Générer les arguments plus tôt pour gérer les erreurs
            try:
                # Utiliser d'abord les arguments par défaut comme fallback
                arguments = self.arguments_par_defaut(
                    data["montant_ttc"],
                    data["economie_mensuelle"],
                    data["economie_annuelle"],
                )

                # Essayer d'obtenir de meilleurs arguments avec Ollama
                try:
                    # Vérifier que le service est disponible avec un timeout court
                    health_check = requests.get(
                        "http://localhost:11434/api/version", timeout=2
                    )

                    if health_check.status_code == 200:
                        ollama_args = self.generer_arguments_ollama(
                            data["montant_ttc"],
                            data["economie_mensuelle"],
                            data["economie_annuelle"],
                            data["prestataire"],
                            data.get("conditions", ""),
                        )

                        # Vérifier que les arguments d'Ollama sont valables
                        if ollama_args and len(ollama_args) == 3:
                            self.log_debug(
                                "Utilisation des arguments d'Ollama dans le PDF"
                            )
                            arguments = ollama_args
                    else:
                        self.log_debug(
                            "Ollama n'est pas disponible (échec healthcheck)"
                        )
                except Exception as conn_err:
                    self.log_debug(
                        f"Impossible de se connecter à Ollama: {str(conn_err)}"
                    )

            except Exception as arg_err:
                self.log_debug(
                    f"Erreur lors de la génération des arguments: {str(arg_err)}"
                )
                # Nous utiliserons les arguments par défaut déjà définis

            # Convertir les arguments pour éviter les problèmes d'encodage
            safe_arguments = []
            for arg in arguments:
                # Utiliser unidecode pour enlever tous les caractères accentués
                safe_arg = unidecode.unidecode(str(arg))
                # Remplacer explicitement les symboles problématiques
                safe_arg = safe_arg.replace("EUR", "euros").replace("€", "euros")
                safe_arguments.append(safe_arg)

            # Créer un PDF simple avec FPDF sans dépendre de polices externes
            pdf = FPDF()
            pdf.add_page()

            # Utiliser uniquement des polices standards intégrées
            pdf.set_font("Arial", "", 12)
            self.log_debug("Utilisation de la police Arial par défaut")

            # Titre
            pdf.set_font("Arial", "B", 16)
            pdf.cell(0, 10, "PROPOSITION COMMERCIALE", ln=True, align="C")
            pdf.set_font("Arial", "B", 14)
            pdf.cell(0, 10, f"Pour {data['prestataire']}", ln=True, align="C")
            pdf.ln(5)

            # Informations économiques (éviter le symbole €)
            pdf.set_font("Arial", "B", 12)
            pdf.cell(0, 10, "ANALYSE ECONOMIQUE", ln=True)
            pdf.set_font("Arial", "", 12)
            pdf.cell(
                0,
                10,
                f"Cout actuel: {self.format_currency(data['montant_ttc'])} EUR/mois",
                ln=True,
            )
            pdf.cell(
                0,
                10,
                f"Economie mensuelle: {self.format_currency(data['economie_mensuelle'])} EUR",
                ln=True,
            )
            pdf.cell(
                0,
                10,
                f"Economie annuelle: {self.format_currency(data['economie_annuelle'])} EUR",
                ln=True,
            )

            # Calcul du pourcentage d'économie
            pourcentage = round(
                (data["economie_mensuelle"] / data["montant_ttc"]) * 100, 1
            )
            pdf.cell(0, 10, f"Pourcentage d'economie: {pourcentage}%", ln=True)

            # Conditions spéciales si disponibles
            if data.get("conditions"):
                pdf.cell(
                    0,
                    10,
                    f"Conditions: {unidecode.unidecode(data.get('conditions'))}",
                    ln=True,
                )

            pdf.ln(5)

            # Arguments de vente
            pdf.set_font("Arial", "B", 12)
            pdf.cell(0, 10, "POURQUOI CHOISIR NOTRE OFFRE", ln=True)
            pdf.set_font("Arial", "", 12)

            # Afficher les arguments
            for i, arg in enumerate(safe_arguments):
                pdf.multi_cell(0, 10, f"{i+1}. {arg}")
                pdf.ln(2)

            # Informations de contact
            pdf.ln(10)
            pdf.set_font("Arial", "I", 10)
            contact_info = f"Document genere le {datetime.now().strftime('%d/%m/%Y')} - Contact: {self.company_info.get('phone', '01 23 45 67 89')}"
            pdf.cell(0, 10, unidecode.unidecode(contact_info), ln=True, align="C")

            # Enregistrer le PDF
            pdf.output(pdf_filename)
            self.log_debug(f"PDF de secours généré avec succès: {pdf_filename}")
            return pdf_filename

        except Exception as e:
            self.log_debug(f"Erreur lors de la génération du PDF de secours: {str(e)}")
            # Version ultra simplifiée en cas d'échec
            return self.generer_pdf_ultra_simple(data)

    def generer_pdf_ultra_simple(self, data):
        """Génération PDF ultra-simple sans formatage complexe pour récupération d'erreur"""
        try:
            # Nom du fichier pour la version de secours
            safe_name = unidecode.unidecode(data["prestataire"]).replace(" ", "_")
            pdf_filename = os.path.join(
                self.pdf_output_dir,
                f"offre_simple_{safe_name}_{datetime.now().strftime('%Y%m%d')}.pdf",
            )

            # Créer un fichier texte qui sera converti en PDF basique
            with open(pdf_filename.replace(".pdf", ".txt"), "w", encoding="utf-8") as f:
                f.write(f"PROPOSITION COMMERCIALE\n\n")
                f.write(f"Client: {data['prestataire']}\n\n")
                f.write(f"Coût actuel: {data['montant_ttc']} EUR/mois\n")
                f.write(f"Économie mensuelle: {data['economie_mensuelle']} EUR\n")
                f.write(f"Économie annuelle: {data['economie_annuelle']} EUR\n\n")
                f.write("POURQUOI CHOISIR NOTRE OFFRE:\n\n")

                # Arguments simplifiés
                f.write("1. Économisez immédiatement sur votre facture mensuelle\n")
                f.write(
                    "2. Bénéficiez d'un service de qualité équivalente ou supérieure\n"
                )
                f.write("3. Profitez d'une offre sans engagement contraignant\n\n")
                f.write(f"Document généré le {datetime.now().strftime('%d/%m/%Y')}")

            # Utiliser FPDF de la manière la plus simple possible
            pdf = FPDF()
            pdf.add_page()
            pdf.set_font("Arial", "", 12)

            # Lire le fichier texte et le convertir en PDF
            with open(pdf_filename.replace(".pdf", ".txt"), "r", encoding="utf-8") as f:
                for line in f:
                    pdf.multi_cell(0, 10, unidecode.unidecode(line.strip()))

            pdf.output(pdf_filename)

            # Supprimer le fichier texte temporaire
            try:
                os.remove(pdf_filename.replace(".pdf", ".txt"))
            except:
                pass

            self.log_debug(f"PDF ultra-simple généré avec succès: {pdf_filename}")
            return pdf_filename
        except Exception as e:
            self.log_debug(f"Échec total de génération PDF: {str(e)}")
            return None

    def traiter_donnees(self):
        """Traite les données JSON et génère le PDF"""
        try:
            with open(self.json_path, "r", encoding="utf-8") as f:
                donnees = json.load(f)

            # Nettoyer les valeurs monétaires et assurer la conversion en nombres
            for key in ["montant_ttc", "economie_mensuelle", "economie_annuelle"]:
                if key in donnees:
                    if isinstance(donnees[key], str):
                        # Nettoyage des valeurs
                        value = unidecode.unidecode(donnees[key])
                        value = (
                            value.replace("€", "")
                            .replace(" ", "")
                            .strip()
                            .replace(",", ".")
                        )
                        donnees[key] = float(value)

            # Essayer d'abord avec le PDF avancé
            pdf_path = self.generer_pdf(donnees)

            # En cas d'échec, utiliser la version de secours
            if not pdf_path:
                self.log_debug("Tentative avec le PDF de secours...")
                pdf_path = self.generer_pdf_secours(donnees)

            if pdf_path:
                # Utiliser un chemin relatif standard
                pdf_url = "../generated_offers/" + os.path.basename(pdf_path)

                return {
                    "success": True,
                    "message": "PDF généré avec succès",
                    "pdf_path": pdf_url,
                }
            else:
                return {"error": "Échec de la génération du PDF"}

        except Exception as e:
            self.log_debug(f"Erreur détaillée: {str(e)}")
            return {"error": f"Erreur lors du traitement des données: {str(e)}"}


# Point d'entrée principal
if __name__ == "__main__":
    try:
        if len(sys.argv) < 2:
            print(json.dumps({"error": "Chemin du fichier JSON non fourni"}))
            sys.exit(1)

        json_path = sys.argv[1]
        print(json.dumps({"debug": f"Traitement du fichier: {json_path}"}))

        if not os.path.exists(json_path):
            print(json.dumps({"error": f"Fichier non trouvé: {json_path}"}))
            sys.exit(1)

        generator = GenerateurOffre(json_path)
        result = generator.traiter_donnees()
        print(json.dumps(result))

    except Exception as e:
        print(
            json.dumps(
                {
                    "error": f"Erreur principale: {str(e)}",
                    "detail": str(type(e).__name__),
                }
            )
        )
