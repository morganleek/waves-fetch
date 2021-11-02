WP Plugin for fetching buoy data via AWS

# File Structure
All files are stored on S3 in a bucket. Create a root directory in that bucket to store all your files. 

`{ S3 Bucket }/{ Waves Root Folder }`

Sitting in the root directory is the `buoys.csv` file which stores all relevent information about the buoys. It tells the website what buoys you have and which need to be checked for updates. This file needs to be updated after each addtion of new wave data by updating the `last_updated` timestamp.

## buoys.csv
This lives in a root directory in an S3 bucket. It is named `buoys.csv`.

`{ S3 Bucket }/{ Waves Root Folder }/buoys.csv`

### Attributes
| Column Heading | Type | Description |
| ------------- | ------------- | ----- |
| `buoy_id` | `integer` | Unique identifier for this buoy **(Required)** |
| `label` | `string` | Label/Slug for internal use I.e. 'PortHeadland'. No spaces. **(Required)** |
| `web_display_name` | `string` | Label for pretty title for the buoy I.e. 'Port Headland Deap Sea' **(Required)** |
| `type` | `string` | Buoy manufacturer |
| `enabled` | `bool` | `0` not visible, `1` visible, `2` map only, `3` chart only  **(Required)** |
| `order` | `integer` | Order of appearance in buoys list on website **(Required)** |
| `data` | `string` | Area to place additional data for later reference or use |
| `start_date` | `integer` | Date buoy was deployed as unix timestamp GMT **(Required)** |
| `end_date` | `integer` | Date buoy was retired as unix timestamp GMT |
| `first_updated` | `integer` | Date first wave data was written as unix timestamp **(Required)** |
| `last_updated` | `integer` | Date most recent update was made as unix timestamp  **(Required)** |
| `latitude` | `string` | Launch latitude |
| `longitude` | `string` | Launch longitude |
| `drifting` | `bool` | `0` if the buoy is anchored and `1` if it's drifiting **(Required)** |
| `download_text` | `string` | Licence for downloading data from website **(Required)** |
| `description` | `string` | Info about the buoy for the website **(Required)** |
| `image` | `string` | URL to image of the buoy **(Required)** |

## Wave Data

Wave data is stored in a foler in the root with **the same name as it's label** in the `buoys.csv`.

`{ S3 Bucket }/{ Waves Root Folder }/{ Buoy Label }/`

Wave data is stored in a folder structure of `/text_archive/{ Year }/{ Month }/{ Day }`. Wave data is stored as a CSV with each day having it's own CSV file. The format for the file name is `{ Buoy Label }_{ Year }{ Month }{ Day }.csv`.

```
// S3 Bucket: 'waves'
// Wave Root Folder: 'wavedata'
// Buoy Label: 'exmouth'
// Date: 2 Nov 2021

waves/wavedata/exmouth/2021/11/02/exmouth_20211102.csv
```