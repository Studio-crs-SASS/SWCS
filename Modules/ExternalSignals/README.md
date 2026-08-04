# SWCS External Signals Module

SWCS External Signals Module は、SWCS本体のWeb巡回結果に、外部サービスの実測値を接続するための拡張モジュールです。

## Purpose

SWCS本体は、Studio-crs独自のWeb巡回・構造確認・リンク確認・導線確認を担当します。

Google Search Console、Bing Webmaster Tools、Google Business Profile などの外部サービス接続は、SWCS本体へ直接混在させず、Modules/ExternalSignals に分離して管理します。

## Scope

- Google Search Console Signals
- Bing Webmaster Tools Signals
- Google Business Profile Signals
- External Signal Aggregation
- SWCS Report Integration
- Future SADS Bridge

## Directory

```text
Modules/ExternalSignals/
├── SearchConsole/
│   ├── Google/
│   └── Bing/
├── BusinessProfile/
│   └── Google/
├── Aggregation/
├── Schema/
├── Samples/
└── README.md

cd ~/Desktop/SWCS

mkdir -p Modules/ExternalSignals/SearchConsole/Google
mkdir -p Modules/ExternalSignals/SearchConsole/Bing
mkdir -p Modules/ExternalSignals/BusinessProfile/Google
mkdir -p Modules/ExternalSignals/Aggregation
mkdir -p Modules/ExternalSignals/Schema
mkdir -p Modules/ExternalSignals/Samples

cat > Modules/ExternalSignals/README.md <<'EOF'
# SWCS External Signals Module

SWCS External Signals Module は、SWCS本体のWeb巡回結果に、外部サービスの実測値を接続するための拡張モジュールです。

## Purpose

SWCS本体は、Studio-crs独自のWeb巡回・構造確認・リンク確認・導線確認を担当します。

Google Search Console、Bing Webmaster Tools、Google Business Profile などの外部サービス接続は、SWCS本体へ直接混在させず、Modules/ExternalSignals に分離して管理します。

## Scope

- Google Search Console Signals
- Bing Webmaster Tools Signals
- Google Business Profile Signals
- External Signal Aggregation
- SWCS Report Integration
- Future SADS Bridge

## Directory

Modules/ExternalSignals/
├── SearchConsole/
│   ├── Google/
│   └── Bing/
├── BusinessProfile/
│   └── Google/
├── Aggregation/
├── Schema/
├── Samples/
└── README.md

## Initial Policy

初期実装ではAPI自動接続を行わず、手動JSON入力から開始します。

Manual JSON Input
↓
ExternalSignalAggregator
↓
SWCS Output JSON
↓
SWCS Report PDF
↓
SADS Diagnosis Input

## Boundary

External Signals Module は、外部実測値の受け取り・整形・SWCS JSONへの統合・Report表示補助を担当します。

AI診断、スコアリング、改善提案、導入提案は行いません。これらはSADSおよびSAISの責務です。

## Version

Specification: SWCS External Signals Module Specification Ver.1.0
Created: 2026-08-03


---

## Manual JSON Sample / 手動JSONサンプル

### Purpose

The first implementation of SWCS ExternalSignals uses manual JSON input.

This allows SWCS to define the data structure for external visibility signals before connecting to official APIs.

### Japanese Purpose

SWCS ExternalSignals の初期実装では、API自動接続ではなく手動JSON入力を使用します。

これにより、Google Search Console、Bing Webmaster Tools、Google Business Profile Performance などの外部可視性データを、正式API接続前にSWCS側の入力形式として固定できます。

---

## Sample File

Modules/ExternalSignals/Samples/life_escortist_external_signals_sample.json

### Input Mode

manual_json

### Target

Life Escortist  
life-escortist.com  
https://life-escortist.com  

---

## Data Structure

The sample JSON contains the following major sections.

data.external_signals.search_console.google  
data.external_signals.search_console.bing  
data.external_signals.business_profile.google  

### Google Search Console

Expected future data:

clicks  
impressions  
ctr  
average_position  
queries  
pages  

### Bing Webmaster Tools

Expected future data:

clicks  
impressions  
ctr  
average_position  
indexed_pages  
crawl_errors  
keywords  
pages  

### Google Business Profile Performance

Expected future data:

profile_views  
search_views  
maps_views  
website_clicks  
phone_calls  
direction_requests  
actions  

---

## Integration Policy

ExternalSignals must remain separated from the SWCS core crawler.

### Core Policy

SWCS Core  
└── Web access / crawl / collection / report  

ExternalSignals Module  
└── Search visibility / business profile visibility / external platform signals  

### Reason

External services require ownership verification, API credentials, access permissions, and future maintenance.

Therefore, ExternalSignals should be handled as an optional module, not as part of the core SWCS crawler.

---

## Next Connection

The manual JSON sample will be used for the following future connections.

01｜SWCS Report  
02｜SADS Mapping  
03｜SAIS Proposal  

### SWCS Report

ExternalSignals can be displayed as an external visibility summary.

### SADS Mapping

ExternalSignals can be used as additional diagnosis material for visibility, search presence, brand discovery, and contact behavior.

### SAIS Proposal

ExternalSignals can support proposal logic for SEO, AIO, Google Business Profile improvement, search visibility improvement, and web route enhancement.

---

## Completion Criteria

ExternalSignals manual JSON sample is considered complete when:

01｜Sample JSON file exists  
02｜JSON syntax check passes  
03｜README documents the sample structure  
04｜GitHub push is complete  
05｜working tree is clean  

