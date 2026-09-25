<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * The documents a diver signs, transcribed as HTML.
 *
 * Transcribed with PADI's permission (obtained by the shop) from:
 *   - Standard Safe Diving Practices Statement of Understanding — Product No. 10060 (Rev. 06/15) Version 2.01, © PADI 2015
 *   - Non-Agency Disclosure and Acknowledgment Agreement / Liability Release and Assumption of Risk Agreement
 *     (Continuing Education) — Product No. 10072 (Rev. 10/16) Version 4.03, © PADI 2016
 *   - Release of Liability / Assumption of Risk / Non-agency Acknowledgment Form — Diver Activities
 *     — Product No. 10086 (Rev. 02/21) Version 3.0, © PADI 2021
 *
 * The wording is PADI's, reproduced faithfully; two typographical slips in
 * the originals ("freedivng", "Parent of Guardian") are corrected. The blanks
 * on the paper forms are filled from the record: participant, store, and —
 * on the training release — the instructor(s). Only English editions were
 * supplied; Spanish readers see the English text with a note.
 *
 * $fills: ['participant' => ..., 'store' => ..., 'instructors' => ..., 'date' => ..., 'dan' => ...]
 */
function form_document_html(string $code, array $fills, string $lang = 'en'): string
{
    $f = static fn (string $k, string $blank = '________________'): string =>
        '<b class="st-fill">' . e(trim((string) ($fills[$k] ?? '')) !== '' ? (string) $fills[$k] : $blank) . '</b>';

    $note = $lang === 'es'
        ? '<p class="st-doc-note">Este documento se presenta en inglés, que es la versión que firmarás. Pregunta al centro si necesitas ayuda para entenderlo.</p>'
        : '';

    switch ($code) {
        case 'safe_diving':
            return $note . <<<HTML
<h4>Standard Safe Diving Practices Statement of Understanding</h4>
<p class="st-doc-meta">Please read carefully before signing.</p>
<p>This is a statement in which you are informed of the established safe diving practices for skin and scuba diving. These practices have been compiled for your review and acknowledgement and are intended to increase your comfort and safety in diving. Your signature on this statement is required as proof that you are aware of these safe diving practices. Read and discuss the statement prior to signing it. If you are a minor, this form must also be signed by a parent or guardian.</p>
<p>I, {$f('participant')}, understand that as a diver I should:</p>
<ol>
<li>Maintain good mental and physical fitness for diving. Avoid being under the influence of alcohol or dangerous drugs when diving. Keep proficient in diving skills, striving to increase them through continuing education and reviewing them in controlled conditions after a period of diving inactivity, and refer to my course materials to stay current and refresh myself on important information.</li>
<li>Be familiar with my dive sites. If not, obtain a formal diving orientation from a knowledgeable, local source. If diving conditions are worse than those in which I am experienced, postpone diving or select an alternate site with better conditions. Engage only in diving activities consistent with my training and experience. Do not engage in cave or technical diving unless specifically trained to do so.</li>
<li>Use complete, well-maintained, reliable equipment with which I am familiar; and inspect it for correct fit and function prior to each dive. Have a buoyancy control device, low-pressure buoyancy control inflation system, submersible pressure gauge and alternate air source and dive planning/monitoring device (dive computer, RDP/dive tables—whichever you are trained to use) when scuba diving. Deny use of my equipment to uncertified divers.</li>
<li>Listen carefully to dive briefings and directions and respect the advice of those supervising my diving activities. Recognize that additional training is recommended for participation in specialty diving activities, in other geographic areas and after periods of inactivity that exceed six months.</li>
<li>Adhere to the buddy system throughout every dive. Plan dives – including communications, procedures for reuniting in case of separation and emergency procedures – with my buddy.</li>
<li>Be proficient in dive planning (dive computer or dive table use). Make all dives no decompression dives and allow a margin of safety. Have a means to monitor depth and time underwater. Limit maximum depth to my level of training and experience. Ascend at a rate of not more than 18 metres/60 feet per minute. Be a SAFE diver – Slowly Ascend From Every dive. Make a safety stop as an added precaution, usually at 5 metres/15 feet for three minutes or longer.</li>
<li>Maintain proper buoyancy. Adjust weighting at the surface for neutral buoyancy with no air in my buoyancy control device. Maintain neutral buoyancy while underwater. Be buoyant for surface swimming and resting. Have weights clear for easy removal, and establish buoyancy when in distress while diving. Carry at least one surface signaling device (such as signal tube, whistle, mirror).</li>
<li>Breathe properly for diving. Never breath-hold or skip-breathe when breathing compressed air, and avoid excessive hyperventilation when breath-hold diving. Avoid overexertion while in and underwater and dive within my limitations.</li>
<li>Use a boat, float or other surface support station, whenever feasible.</li>
<li>Know and obey local dive laws and regulations, including fish and game and dive flag laws.</li>
</ol>
<p>I understand the importance and purposes of these established practices. I recognize they are for my own safety and well-being, and that failure to adhere to them can place me in jeopardy when diving.</p>
<p class="st-doc-meta">Product No. 10060 (Rev. 06/15) Version 2.01 · © PADI 2015</p>
HTML;

        case 'liability':
            return $note . <<<HTML
<h4>Non-Agency Disclosure and Acknowledgment Agreement</h4>
<p class="st-doc-meta">Please read carefully and fill in all blanks before signing. In European Union and European Free Trade Association countries use alternative form.</p>
<p>I understand and agree that PADI Members (“Members”), including {$f('store', '(store/resort)')} and/or any individual PADI Instructors and Divemasters associated with the program in which I am participating, are licensed to use various PADI Trademarks and to conduct PADI training, but are not agents, employees or franchisees of PADI Americas, Inc, or its parent, subsidiary and affiliated corporations (“PADI”). I further understand that Member business activities are independent, and are neither owned nor operated by PADI, and that while PADI establishes the standards for PADI diver training programs, it is not responsible for, nor does it have the right to control, the operation of the Members’ business activities and the day-to day conduct of PADI programs and supervision of divers by the Members or their associated staff. I further understand and agree on behalf of myself, my heirs and my estate that in the event of an injury or death during this activity, neither I nor my estate shall seek to hold PADI liable for the actions, inactions or negligence of {$f('store', '(store/resort)')} and/or the instructors and divemasters associated with the activity.</p>
<h4>Liability Release and Assumption of Risk Agreement</h4>
<p class="st-doc-meta">Please read carefully and fill in all blanks before signing. In European Union and European Free Trade Association countries use alternative form.</p>
<p>I, {$f('participant')}, hereby affirm that I am aware that skin and scuba diving have inherent risks which may result in serious injury or death.</p>
<p>I understand that diving with compressed air involves certain inherent risks; including but not limited to decompression sickness, embolism or other hyperbaric/air expansion injury that require treatment in a recompression chamber. I further understand that the open water diving trips which are necessary for training and for certification may be conducted at a site that is remote, either by time or distance or both, from such a recompression chamber. I still choose to proceed with such instructional dives in spite of the possible absence of a recompression chamber in proximity to the dive site.</p>
<p>I understand and agree that neither my instructor(s), {$f('instructors', '(instructor)')}, the facility through which I receive my instruction, {$f('store', '(store/resort)')}, nor PADI Americas, Inc., nor its affiliate and subsidiary corporations, nor any of their respective employees, officers, agents, contractors or assigns (hereinafter referred to as “Released Parties”) may be held liable or responsible in any way for any injury, death or other damages to me, my family, estate, heirs or assigns that may occur as a result of my participation in this diving program or as a result of the negligence of any party, including the Released Parties, whether passive or active.</p>
<p>In consideration of being allowed to participate in this course (and optional Adventure Dive), hereinafter referred to as “program,” I hereby personally assume all risks of this program, whether foreseen or unforeseen, that may befall me while I am a participant in this program including, but not limited to, the academics, confined water and/or open water activities.</p>
<p>I further release, exempt and hold harmless said program and Released Parties from any claim or lawsuit by me, my family, estate, heirs or assigns, arising out of my enrollment and participation in this program including both claims arising during the program or after I receive my certification.</p>
<p>I also understand that skin diving and scuba diving are physically strenuous activities and that I will be exerting myself during this program, and that if I am injured as a result of heart attack, panic, hyperventilation, drowning or any other cause, that I expressly assume the risk of said injuries and that I will not hold the Released Parties responsible for the same.</p>
<p>I further state that I am of lawful age and legally competent to sign this liability release, or that I have acquired the written consent of my parent or guardian. I understand the terms herein are contractual and not a mere recital, and that I have signed this Agreement of my own free act and with the knowledge that I hereby agree to waive my legal rights. I further agree that if any provision of this Agreement is found to be unenforceable or invalid, that provision shall be severed from this Agreement. The remainder of this Agreement will then be construed as though the unenforceable provision had never been contained herein.</p>
<p>I understand and agree that I am not only giving up my right to sue the Released Parties but also any rights my heirs, assigns, or beneficiaries may have to sue the Released Parties resulting from my death. I further represent I have the authority to do so and that my heirs, assigns, or beneficiaries will be estopped from claiming otherwise because of my representations to the Released Parties.</p>
<p class="st-doc-caps">I, {$f('participant')}, BY THIS INSTRUMENT AGREE TO EXEMPT AND RELEASE MY INSTRUCTORS, {$f('instructors', '(instructor)')}, THE FACILITY THROUGH WHICH I RECEIVE MY INSTRUCTION, {$f('store', '(store/resort)')}, AND PADI AMERICAS, INC., AND ALL RELATED ENTITIES AS DEFINED ABOVE, FROM ALL LIABILITY OR RESPONSIBILITY WHATSOEVER FOR PERSONAL INJURY, PROPERTY DAMAGE OR WRONGFUL DEATH HOWEVER CAUSED, INCLUDING, BUT NOT LIMITED TO, THE NEGLIGENCE OF THE RELEASED PARTIES, WHETHER PASSIVE OR ACTIVE.</p>
<p class="st-doc-caps">I HAVE FULLY INFORMED MYSELF AND MY HEIRS OF THE CONTENTS OF THIS NON-AGENCY DISCLOSURE AND ACKNOWLEDGEMENT AGREEMENT AND LIABILITY RELEASE AND ASSUMPTION OF RISK AGREEMENT BY READING BOTH BEFORE SIGNING BELOW ON BEHALF OF MYSELF AND MY HEIRS.</p>
<p class="st-doc-meta">Product No. 10072 (Rev. 10/16) Version 4.03 · © PADI 2016</p>
HTML;

        case 'liability_excursion':
            return $note . <<<HTML
<h4>Release of Liability / Assumption of Risk / Non-agency Acknowledgment Form — Diver Activities</h4>
<p class="st-doc-meta">Please read carefully and fill in all blanks before signing.</p>
<h4>Non-Agency Disclosure and Acknowledgment Agreement</h4>
<p>I understand and agree that PADI Members (“Members”), including {$f('store', '(store/resort)')}, and/or any individual PADI Instructors and Divemasters associated with the program in which I am participating, are licensed to use various PADI Trademarks and to conduct PADI training, but are not agents, employees or franchisees of PADI Americas, Inc., or its parent, subsidiary and affiliated corporations (“PADI”). I further understand that Member business activities are independent, and are neither owned nor operated by PADI, and that while PADI establishes the standards for PADI diver training programs, it is not responsible for, nor does it have the right to control, the operation of the Members’ business activities and the day-to-day conduct of PADI programs and supervision of divers by the Members or their associated staff. I further understand and agree on behalf of myself, my heirs and my estate that in the event of an injury or death during this activity, neither I nor my estate shall seek to hold PADI liable for the actions, inactions or negligence of the entities listed above and/or the instructors and divemasters associated with the activity.</p>
<h4>Liability Release and Assumption of Risk Agreement</h4>
<p>I, {$f('participant')}, hereby affirm that I am a certified scuba diver trained in safe dive practices, or a student diver under the control and supervision of a certified scuba instructor. I know that skin diving, freediving and scuba diving have inherent risks including those risks associated with boat travel to and from the dive site (hereinafter “Excursion”), which may result in serious injury or death. I understand that scuba diving with compressed air involves certain inherent risks; including but not limited to decompression sickness, embolism or other hyperbaric/air expansion injury that require treatment in a recompression chamber. If I am scuba diving with oxygen enriched air (“Enriched Air”) or other gas blends including oxygen, I also understand that it involves inherent risks of oxygen toxicity and/or improper mixtures of breathing gas. I acknowledge this Excursion includes risks of slipping or falling while on board the boat, being cut or struck by a boat while in the water, injuries occurring while getting on or off a boat, and other perils of the sea. I further understand that the Excursion will be conducted at a site that is remote, either by time or distance or both, from a recompression chamber. I still choose to proceed with the Excursion in spite of the absence of a recompression chamber in proximity to the dive site(s).</p>
<p>I understand and agree that neither {$f('store', '(store/resort)')}; nor the dive professional(s) who may be present at the dive site, nor PADI Americas, Inc., nor any of their affiliate and subsidiary corporations, nor any of their respective employees, officers, agents, contractors and assigns (hereinafter “Released Parties”) may be held liable or responsible in any way for any injury, death or other damages to me, my family, estate, heirs or assigns that may occur during the Excursion as a result of my participation in the Excursion or as a result of the negligence of any party, including the Released Parties, whether passive or active.</p>
<p>I affirm I am in good mental and physical fitness for the Excursion. I further state that I will not participate in the Excursion if I am under the influence of alcohol or any drugs that are contraindicated to diving. If I am taking medication, I affirm that I have seen a physician and have approval to dive while under the influence of the medication/drugs. I understand that diving is a physically strenuous activity and that I will be exerting myself during the Excursion and that if I am injured as a result of heart attack, panic, hyperventilation, drowning or any other cause, that I expressly assume the risk of said injuries and that I will not hold the Released Parties responsible for the same.</p>
<p>I am aware that safe dive practices suggest diving with a buddy unless trained as a self-reliant diver. I am aware it is my responsibility to plan my dive allowing for my diving experience and limitations, and the prevailing water conditions and environment. I will not hold the Released Parties responsible for my failure to safely plan my dive, dive my plan, and follow the instructions and dive briefing of the dive professional(s).</p>
<p>If diving from a boat, I will be present at and attentive to the briefing given by the boat crew. If there is anything I do not understand I will notify the boat crew or captain immediately. I acknowledge it is my responsibility to plan my dives as no-decompression dives, and within parameters that allow me to make a safety stop before ascending to the surface, arriving on board the vessel with gas remaining in my cylinder as a measure of safety. If I become distressed on the surface I will immediately drop my weights and inflate my BCD (orally or with low pressure inflator) to establish buoyancy on the surface.</p>
<p>I am aware safe dive practices recommend a refresher or guided orientation dive following a period of diving inactivity. I understand such refresher/guided dive is available for an additional fee. If I choose not to follow this recommendation I will not hold the Released Parties responsible for my decision.</p>
<p>I acknowledge Released Parties may provide an in-water guide (hereinafter “Guide”) during the Excursion. The Guide is present to assist in navigation during the dive and identifying local flora and fauna. If I choose to dive with the Guide I acknowledge it is my responsibility to stay in proximity to the Guide during the dive. I assume all risks associated with my choice whether to dive in proximity to the Guide or to dive independent of the Guide. I acknowledge my participation in diving is at my own risk and peril.</p>
<p>I affirm it is my responsibility to inspect all of the equipment I will be using prior to the leaving the dock for the Excursion and that I should not dive if the equipment is not functioning properly. I will not hold the Released Parties responsible for my failure to inspect the equipment prior to diving or if I choose to dive with equipment that may not be functioning properly.</p>
<p>I acknowledge Released Parties have made no representation to me, implied or otherwise, that they or their crew can or will perform affective rescues or render first aid. In the event I show signs of distress or call for aid I would like assistance and will not hold the Released Parties, their crew, dive boats or passengers responsible for their actions in attempting the performance of rescue or first aid.</p>
<p>I hereby state and agree that this Agreement will be effective for all Excursions in which I participate for one (1) year from the date on which I sign this Agreement.</p>
<p>I further state that I am of lawful age and legally competent to sign this liability release, or that I have acquired the written consent of my parent or guardian. I understand the terms herein are contractual and not a mere recital, and that I have signed this Agreement of my own free act and with the knowledge that I hereby agree to waive my legal rights. I further agree that if any provision of this Agreement is found to be unenforceable or invalid, that provision shall be severed from this Agreement. The remainder of this Agreement will then be construed as though the unenforceable provision had never been contained herein. I understand and agree that I am not only giving up my right to sue the Released Parties but also any rights my heirs, assigns, or beneficiaries may have to sue the Released Parties resulting from my death. I further represent that I have the authority to do so and that my heirs, assigns, and beneficiaries will be estopped from claiming otherwise because of my representations to the Released Parties.</p>
<p class="st-doc-caps">I, {$f('participant')}, BY THIS INSTRUMENT, AGREE TO EXEMPT AND RELEASE THE RELEASED PARTIES DEFINED ABOVE FROM ALL LIABILITY OR RESPONSIBILITY WHATSOEVER FOR PERSONAL INJURY, PROPERTY DAMAGE OR WRONGFUL DEATH HOWEVER CAUSED, INCLUDING BUT NOT LIMITED TO THE NEGLIGENCE OF THE RELEASED PARTIES, WHETHER PASSIVE OR ACTIVE.</p>
<p class="st-doc-caps">I HAVE FULLY INFORMED MYSELF AND MY HEIRS OF THE CONTENTS OF THIS NON-AGENCY DISCLOSURE AND ACKNOWLEDGMENT AGREEMENT, AND LIABILITY RELEASE AND ASSUMPTION OF RISK AGREEMENT BY READING BOTH BEFORE SIGNING BELOW ON BEHALF OF MYSELF AND MY HEIRS.</p>
<p>Diver Accident Insurance: {$f('dan', 'NO')}</p>
<p class="st-doc-meta">Product No. 10086 (Rev. 02/21) Version 3.0 · © PADI 2021</p>
HTML;
    }

    return '';
}
