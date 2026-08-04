# SWCS - Syu Web Check System

## 01. システム位置づけ

SWCS - Syu Web Check System は、SEEN - Studio-crs Evolve Ecosystem Network におけるWeb確認用Satellite Engineである。

SWCSは、SADS - Syu AI Diagnosis System に進む前段階として、対象Webサイトの取得状態・構造・情報量・導線を確認する。

SWCSは、診断・採点・評価を行わない。
役割は、対象Webサイトの現状を取得・確認・可視化することである。

---

## 02. SWCS Client Report Sheet Ver.1.0

SWCS Client Report Sheet Ver.1.0 は、クライアント提示用のWeb確認レポートである。

対象Webサイトの取得状態、構造、確認領域、キーワード候補、リンク・メディア・導線、Studio-crsコメント、次工程への流れを、PDFとして提示できる形に整備した。

---

## 03. 完成した内容

SWCS Client Report Sheet Ver.1.0 として、以下の内容が完成した。

* クライアント提示用PDFレポート画面
* `Public/report.php`
* レポート確認用の基準JSON
* 7ページ構成のPDFレイアウト
* Executive View
* SWCS Check Coverage Map
* Current Site Coverage
* SWCS Standard Coverage Model
* Check Coverage
* Detailed Findings
* Keyword Candidates
* Studio-crs Comment
* Next Step Flow

---

## 04. レポートURL例

ローカル環境でのレポート確認URL例：

```text
http://localhost:8001/report.php?file=life_escortist_swcs_output_v2.json&client=Life%20Escortist&staff=Syuji%20Konishi
```

---

## 05. PDF保存時の注意

ブラウザの印刷画面からPDF保存する場合は、ヘッダーとフッターをOFFにする。

推奨設定：

```text
ヘッダーとフッター：OFF
```

これにより、ブラウザ側で自動表示される日時、ページ番号、localhost情報がPDFに入ることを防ぐ。

---

## 06. SEEN内での流れ

SWCSは、SEEN営業導線において最初のWeb確認工程を担当する。

```text
SWCS Web Check
↓
Client Confirmation
↓
SADS AI Diagnosis
↓
SAIS Introduction Proposal
↓
SASS Operation / Advisory
```

---

## 07. SADSへの引き継ぎ前提

SWCSは、対象Webサイトへアクセスできるか、どのような情報が取得できるかを確認する。

SWCSで取得・確認したWebサイトの現状は、SADSへ進むための前提情報となる。

SADSでは、そのSWCS出力をもとにAI診断・採点・レポート生成を行う。

---

## 08. SWCSの責務

SWCSの責務は以下である。

1. 対象Webサイトへアクセスする
2. HTMLを取得する
3. 取得状態を確認する
4. 構造情報を整理する
5. キーワード候補を抽出する
6. リンク・メディア・導線を確認する
7. クライアントに提示できる形で可視化する

---

## 09. SWCSの境界

SWCSは以下を行わない。

1. AI診断
2. 採点
3. 評価
4. 改善提案の決定
5. 導入見積
6. 運用設計

これらは、後続工程であるSADS、SAIS、SASSが担当する。

---

## 10. 現在の状態

```text
SWCS Client Report Sheet Ver.1.0 完成
GitHubバックアップ完了
終業時 clean 状態確認済み
```

---

## 11. 完了日

2026年08月01日
SWCS Client Report Sheet Ver.1.0 完成

---

## SWCS Report Ver.1.1 / Sales Layout

### Purpose

SWCS Report Ver.1.1 is a client-facing web check report designed to visualize the current acquisition status, structure, content volume, links, routes, and crawl coverage of the target website before proceeding to SADS AI Diagnosis.

This report is not a diagnosis or score report.  
It is a pre-diagnosis visibility report used to confirm whether enough web information has been collected for the next SADS process.

### Japanese Purpose

SWCS Report Ver.1.1 は、SADS（AI診断）へ進む前に、対象Webサイトの取得状態・構造・情報量・リンク・導線・巡回範囲を可視化するクライアント提示用レポートです。

本レポートは診断・採点レポートではありません。  
次工程である SADS に進むために、Web情報がどこまで取得・確認できているかを整理するための現状可視化レポートです。

---

## Report URL

### Standard Report URL

http://localhost:8001/report.php?file=life_escortist_swcs_output_v2.json&client=Life%20Escortist&staff=Syuji%20Konishi

### Site Crawl Report URL

http://localhost:8001/report.php?file=life_escortist_swcs_output_site_crawl_api_test.json&client=Life%20Escortist&staff=Syuji%20Konishi

### URL Parameters

| Parameter | Description |
|---|---|
| file | JSON output file name located in `Data/output/` |
| client | Client display name |
| staff | Studio-crs staff display name |

---

## Report Layout

SWCS Report Ver.1.1 uses the following 7-page sales layout.

Page 1｜Cover / Site Visibility Summary / Executive View  
Page 2｜SWCS Check Coverage Map / Coverage Meaning  
Page 3｜01. Check Coverage  
Page 4｜02. Detailed Findings  
Page 5｜02. Detailed Findings / Links / Brand Connection / Contact Route  
Page 6｜02. Detailed Findings / Flow / Crawl Summary / Performance / Validation  
Page 7｜03. Studio-crs Comment / Next Step Flow  

### Layout Role

| Section | Role |
|---|---|
| Site Visibility Summary | Shows key collected web information at a glance |
| Executive View | Explains the report role and current status |
| SWCS Check Coverage Map | Visualizes the web areas confirmed by SWCS |
| Coverage Meaning | Explains why the coverage map matters before SADS |
| Check Coverage | Lists the 10 SWCS confirmation areas |
| Detailed Findings | Shows collected information by category |
| Brand Connection | Shows external brand-related routes |
| Contact Route | Shows inquiry routes such as email links |
| Crawl Summary | Shows site crawl mode, visited pages, success count, and limit status |
| Next Step Flow | Connects SWCS to Client Confirmation and SADS AI Diagnosis |

---

## Site Visibility Summary

The cover page includes a compact Site Visibility Summary.

### Display Items

確認ページ数  
取得本文量  
総リンク数  
ブランド接続  
問い合わせ導線  

### Purpose

The Site Visibility Summary allows the client to immediately understand how much web information was confirmed by SWCS.

It helps make the report easier to understand before reading the detailed findings.

---

## Coverage Meaning

The second page includes a Coverage Meaning block under the SWCS Check Coverage Map.

### Display Items

01｜取得できた領域  
02｜SADSへ渡せる材料  
03｜次に深掘りする領域  

### Purpose

Coverage Meaning explains that the coverage map is not a score or evaluation.  
It shows which information areas were confirmed and which areas can be passed to SADS AI Diagnosis.

### Japanese Purpose

Coverage Meaning は、SWCS Check Coverage Map が点数や評価ではなく、SADSへ進む前の確認領域を示すものであることを説明します。

SWCSで確認できた領域は、次工程SADSの診断・スコアリング材料になります。

---

## Brand Connection

Brand Connection displays confirmed external brand-related links.

### Example

Brand Connection / ブランド接続確認  
リンクURL：https://studio-crs.com/  
導線ページ：https://life-escortist.com/  
リンクテキスト：Studio-crs  

### Purpose

Brand Connection converts ordinary external link data into client-understandable brand route information.

---

## Contact Route

Contact Route displays confirmed inquiry routes such as email links.

### Example

Contact Route / 問い合わせ導線確認  
問い合わせ先：support-jp@life-escortist.com  
取得元ページ：https://life-escortist.com/jp  
リンクテキスト：ご用命  

### Purpose

Contact Route converts ordinary email link data into client-understandable inquiry route information.

---

## Next Step Flow

The final page includes the following process.

01｜SWCS Web Check  
02｜Client Confirmation  
03｜SADS AI Diagnosis  

### SADS AI Diagnosis Description

SWCSで取得したWeb情報をもとに、SADSで情報量・構造・導線・接続状態をスコア化し、改善優先度と次工程の導入提案へ整理します。

### Purpose

The Next Step Flow makes it clear that SWCS is the first step before SADS AI Diagnosis and later proposal / introduction processes.

---

## PDF Export Notes

When exporting the report as PDF from the browser, use the following print settings.

Destination：Save as PDF  
Headers and footers：Off  
Background graphics：On  
Paper size：A4  
Scale：Default or Fit to printable area  

The report is designed to be used as a client-facing PDF sales material.

